<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Console\Command;

use DOMDocument;
use Exception;
use LibXMLError;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\DomDocument\DomDocumentFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Composite;
use Magento\Framework\Phrase\RendererInterface;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Style\SymfonyStyleFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\FinderFactory;

use function __;
use function array_column;
use function array_map;
use function array_unshift;
use function array_walk;
use function basename;
use function count;
use function getenv;
use function in_array;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function ltrim;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;

use const BP;

class ValidateXmlCommand extends Command
{
    public const COMMAND_NAME = 'dev:xml:validate';
    private const EXCLUDED_FILES = [
        '.phpcs.xml',
        'phpcs.xml',
        'phpunit.xml',
    ];

    private RendererInterface $renderer;
    private SymfonyStyleFactory $symfonyStyleFactory;
    private DomDocumentFactory $domDocumentFactory;
    private File $driver;
    private FinderFactory $finderFactory;
    private UrnResolver $urnResolver;
    private bool $isEnvironmentGitHubActions = false;
    private OutputInterface $output;
    private SymfonyStyle $symfonyStyle;

    public function __construct(
        RendererInterface $renderer,
        SymfonyStyleFactory $symfonyStyleFactory,
        DomDocumentFactory $domDocumentFactory,
        File $driver,
        FinderFactory $finderFactory,
        UrnResolver $urnResolver,
        string $name = null
    ) {
        $this->renderer = $renderer;
        $this->symfonyStyleFactory = $symfonyStyleFactory;
        $this->domDocumentFactory = $domDocumentFactory;
        $this->driver = $driver;
        $this->finderFactory = $finderFactory;
        $this->urnResolver = $urnResolver;

        parent::__construct($name);

        $this->fixTranslationRenderer();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Validates an XML file against its configured schema');
        $this->addArgument(
            'paths',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'List of files or directories to validate'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->isEnvironmentGitHubActions = (bool)getenv('GITHUB_ACTIONS');
        $this->output = $output;
        $this->symfonyStyle = $this->symfonyStyleFactory->create(
            [
                'input' => $input,
                'output' => $this->output
            ]
        );
        $paths = $input->getArgument('paths');
        /** @var Finder $finder */
        $finder = $this->finderFactory->create();
        $fileCount = 0;
        $validFiles = 0;

        if ($this->isEnvironmentGitHubActions) {
            $this->output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $this->symfonyStyle->title((string)__('Imagination Media XML Validator'));

        array_walk(
            $paths,
            function (string $path) use ($finder, &$fileCount, &$validFiles): void {
                if ($this->driver->isFile($path)) {
                    $fileName = basename($path);

                    if (in_array($fileName, self::EXCLUDED_FILES)) {
                        $this->outputWarnings(
                            [
                                [
                                    'file' => $fileName,
                                    'line' => 0,
                                    'message' => (string)__('File "%1" is not a Magento XML file.', $fileName)
                                ]
                            ]
                        );

                        return;
                    }

                    $xmlFiles = [$path];
                } else {
                    $xmlFiles = $finder->files()
                        ->name('*.xml')
                        ->notName(self::EXCLUDED_FILES)
                        ->in($path);
                }

                $fileCount += count($xmlFiles);

                if (count($xmlFiles) === 0) {
                    return;
                }

                foreach ($xmlFiles as $xmlFile) {
                    if ($xmlFile instanceof SplFileInfo) {
                        $xml = $xmlFile->getContents();
                        $relativePath = str_replace(BP, '', $xmlFile->getPathname());
                    } else {
                        $xml = $this->driver->fileGetContents($xmlFile);
                        $relativePath = $this->driver->getRelativePath(BP, $path); /* @phpstan-ignore-line */
                    }

                    $relativePath = ltrim($relativePath, '\\/');

                    try {
                        $isValid = $this->validateXml($xml, $relativePath);
                    } catch (Exception $e) {
                        $isValid = false;

                        $this->outputErrors(
                            [
                                [
                                    'file' => $relativePath,
                                    'line' => 0,
                                    'message' => (string)__(
                                        'Could not process %1. Error: %2',
                                        (string)$xmlFile,
                                        $e->getMessage()
                                    )
                                ]
                            ]
                        );
                    }

                    if ($isValid) {
                        $validFiles++;
                    }
                }
            }
        );

        $this->symfonyStyle->writeln(
            (string)__(
                '{valid_files} of {total_files, plural, =1{# file is} other{# files are}} valid',
                [
                    'valid_files' => $validFiles,
                    'total_files' => $fileCount
                ]
            )
        );

        return $validFiles === $fileCount ? 0 : 1;
    }

    /**
     * @throws Exception
     */
    private function validateXml(string $xml, string $fileName): bool
    {
        /** @var DOMDocument $domDocument */
        $domDocument = $this->domDocumentFactory->create();

        libxml_use_internal_errors(true);

        $domDocument->loadXML($xml);

        $errors = libxml_get_errors();

        libxml_use_internal_errors(false);
        libxml_clear_errors();

        preg_match('/xsi:noNamespaceSchemaLocation=\s*"(urn:[^"]+)"/s', $xml, $schemaLocations);

        if (count($schemaLocations) === 0) {
            $this->outputWarnings(
                [
                    [
                        'file' => $fileName,
                        'line' => 0,
                        'message' => (string)__('XML file "%1" does not have a Magento schema defined', $fileName)
                    ]
                ]
            );

            return false;
        }

        if (count($errors) > 0) {
            $errors = array_map(
                static fn(LibXMLError $error): array => [
                    'file' => $fileName,
                    'line' => $error->line,
                    'message' => sprintf('Line %d: %s', $error->line, rtrim($error->message, "\n")),
                ],
                $errors
            );

            $this->outputErrors($errors);

            return false;
        }

        $schemaPath = ltrim(
            $this->driver->getRelativePath(BP, $this->urnResolver->getRealPath($schemaLocations[1])),
            '\\/'
        );

        $this->symfonyStyle->text((string)__('Validating %1 against %2...', $fileName, $schemaPath));
        $this->symfonyStyle->newLine();

        $errors = Dom::validateDomDocument($domDocument, $schemaLocations[1], "Line %line%: %message%\n");

        if (count($errors) > 0) {
            $errors = array_map(
                static fn(string $error): array => [
                    'file' => $fileName,
                    'line' => (int)preg_replace('/^Line (\d+).+/', '$1', $error),
                    'message' => $error
                ],
                $errors
            );

            $this->outputErrors($errors);

            return false;
        }

        $this->symfonyStyle->success((string)__('XML is valid.'));

        return true;
    }

    /**
     * @param array{
     *     file: string,
     *     line: int,
     *     message: string
     * }[] $errors
     */
    private function outputErrors(array $errors): void
    {
        if ($this->isEnvironmentGitHubActions) {
            $this->outputToGitHubActions($errors);

            return;
        }

        $errors = array_column($errors, 'message');

        array_unshift($errors, (string)__('Invalid XML. Errors:'));

        $this->symfonyStyle->error($errors);
    }

    /**
     * @param array{
     *     file: string,
     *     line: int,
     *     message: string
     * }[] $warnings
     */
    private function outputWarnings(array $warnings): void
    {
        if ($this->isEnvironmentGitHubActions) {
            $this->outputToGitHubActions($warnings, 'warning');

            return;
        }

        $warnings = array_column($warnings, 'message');

        $this->symfonyStyle->warning($warnings);
    }

    /**
     * @param array{
     *     file: string,
     *     line: int,
     *     message: string
     * }[] $messages
     */
    private function outputToGitHubActions(array $messages, string $level = 'error'): void
    {
        array_walk(
            $messages,
            function (array $message) use ($level): void {
                $output = sprintf(
                    '::%s file=%s,line=%d,col=0::%s',
                    $level,
                    $message['file'],
                    $message['line'],
                    str_replace("\n", '%0A', preg_replace('/^Line \d+:\s(.+)/', '$1', $message['message']) ?? '')
                );

                $this->output->write($output, false, OutputInterface::VERBOSITY_QUIET);
                $this->output->writeln('', OutputInterface::VERBOSITY_QUIET);
            }
        );
    }

    private function fixTranslationRenderer(): void
    {
        /* Fixes bug where incorrect translation renderer is used for Console commands, causing strings formatted for
           `MessageFormatter` to not render correctly. */

        if (Phrase::getRenderer() instanceof Composite) {
            return;
        }

        Phrase::setRenderer($this->renderer);
    }
}
