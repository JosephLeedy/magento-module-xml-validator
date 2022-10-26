<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Console\Command;

use DOMDocument;
use Exception;
use LibXMLError;
use Magento\Framework\Config\Dom;
use Magento\Framework\DomDocument\DomDocumentFactory;
use Magento\Framework\Filesystem\DriverInterface;
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
use function array_map;
use function array_unshift;
use function array_walk;
use function basename;
use function count;
use function in_array;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function rtrim;
use function sprintf;
use function strrpos;
use function substr;

class ValidateXmlCommand extends Command
{
    public const COMMAND_NAME = 'dev:xml:validate';
    private const EXCLUDED_FILES = [
        '.phpcs.xml',
        'phpcs.xml',
        'phpunit.xml',
    ];

    private SymfonyStyleFactory $symfonyStyleFactory;
    private DomDocumentFactory $domDocumentFactory;
    private DriverInterface $driver;
    private FinderFactory $finderFactory;
    private SymfonyStyle $symfonyStyle;

    public function __construct(
        SymfonyStyleFactory $symfonyStyleFactory,
        DomDocumentFactory $domDocumentFactory,
        DriverInterface $driver,
        FinderFactory $finderFactory,
        string $name = null
    ) {
        $this->symfonyStyleFactory = $symfonyStyleFactory;
        $this->domDocumentFactory = $domDocumentFactory;
        $this->driver = $driver;
        $this->finderFactory = $finderFactory;

        parent::__construct($name);
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
        $this->symfonyStyle = $this->symfonyStyleFactory->create(
            [
                'input' => $input,
                'output' => $output
            ]
        );
        $paths = $input->getArgument('paths');
        /** @var Finder $finder */
        $finder = $this->finderFactory->create();
        $fileCount = 0;
        $validFiles = 0;

        $this->symfonyStyle->title((string)__('Imagination Media XML Validator'));

        array_walk(
            $paths,
            function (string $path) use ($finder, &$fileCount, &$validFiles): void {
                if ($this->driver->isFile($path)) {
                    $fileName = basename($path);

                    if (in_array($fileName, self::EXCLUDED_FILES)) {
                        $this->symfonyStyle->warning((string)__('File "%1" is not a Magento XML file.', $fileName));

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
                    } else {
                        $xml = $this->driver->fileGetContents($xmlFile);
                    }

                    try {
                        $isValid = $this->validateXml($xml, (string)$xmlFile);
                    } catch (Exception $e) {
                        $isValid = false;

                        $this->outputErrors(
                            [
                                (string)__('Could not process %1. Error: %2', (string)$xmlFile, $e->getMessage())
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
            $this->symfonyStyle->warning((string)__('XML file "%1" does not have a Magento schema defined', $fileName));

            return false;
        }

        if (count($errors) > 0) {
            $errors = array_map(
                static fn(LibXMLError $error): string
                    => sprintf('Line %d: %s', $error->line, rtrim($error->message, "\n")),
                $errors
            );

            $this->outputErrors($errors);

            return false;
        }

        $schemaName = substr($schemaLocations[1], strrpos($schemaLocations[1], ':' ) + 1);

        $this->symfonyStyle->text((string)__('Validating %1 against %2...', $fileName, $schemaName));
        $this->symfonyStyle->newLine();

        $errors = Dom::validateDomDocument($domDocument, $schemaLocations[1], "Line %line%: %message%\n");

        if (count($errors) > 0) {
            $this->outputErrors($errors);

            return false;
        }

        $this->symfonyStyle->success((string)__('XML is valid.'));

        return true;
    }

    /**
     * @param string[] $errors
     */
    private function outputErrors(array $errors): void
    {
        array_unshift($errors, (string)__('Invalid XML. Errors:'));

        $this->symfonyStyle->error($errors);
    }
}
