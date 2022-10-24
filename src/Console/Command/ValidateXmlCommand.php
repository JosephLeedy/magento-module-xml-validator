<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Console\Command;

use DOMDocument;
use Exception;
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
use function array_unshift;
use function array_walk;
use function count;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function strrpos;
use function substr;

class ValidateXmlCommand extends Command
{
    public const COMMAND_NAME = 'dev:xml:validate';

    private SymfonyStyleFactory $symfonyStyleFactory;
    private DomDocumentFactory $domDocumentFactory;
    private DriverInterface $driver;
    private FinderFactory $finderFactory;

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
        /** @var SymfonyStyle $symfonyStyle */
        $symfonyStyle = $this->symfonyStyleFactory->create(
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

        $symfonyStyle->title((string)__('Imagination Media XML Validator'));

        array_walk(
            $paths,
            function (string $path) use ($symfonyStyle, $finder, &$fileCount, &$validFiles): void {
                if ($this->driver->isFile($path)) {
                    $xmlFiles = [$path];
                } else {
                    $xmlFiles = $finder->files()
                        ->name('*.xml')
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
                        $isValid = $this->validateXml($xml, (string)$xmlFile, $symfonyStyle);
                    } catch (Exception $e) {
                        $isValid = false;

                        $symfonyStyle->error((string)__('Could not process %1. Error: %2', $e->getMessage()));
                    }

                    if ($isValid) {
                        $validFiles++;
                    }
                }
            }
        );

        $symfonyStyle->writeln(
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
    private function validateXml(string $xml, string $fileName, SymfonyStyle $symfonyStyle): bool
    {
        /** @var DOMDocument $domDocument */
        $domDocument = $this->domDocumentFactory->create();

        libxml_use_internal_errors(true);

        $domDocument->loadXML($xml);

        $errors = libxml_get_errors();

        libxml_clear_errors();

        if (count($errors) > 0) {
            array_unshift($errors, (string)__('Could not load %1 as XML. Errors:', $fileName));

            $symfonyStyle->error($errors);

            return false;
        }

        preg_match('/xsi:noNamespaceSchemaLocation=\s*"(urn:[^"]+)"/s', $xml, $schemaLocations);

        if (count($schemaLocations) === 0) {
            $symfonyStyle->warning((string)__('XML file "%1" does not have a Magento schema defined', $fileName));

            return false;
        }

        $schemaName = substr($schemaLocations[1], strrpos($schemaLocations[1], ':' ) + 1);

        $symfonyStyle->text((string)__('Validating %1 against %2...', $fileName, $schemaName));
        $symfonyStyle->newLine();

        $errors = Dom::validateDomDocument($domDocument, $schemaLocations[1], "Line %line%: %message%\n");

        if (count($errors) > 0) {
            array_unshift($errors, (string)__('Invalid XML. Errors:'));

            $symfonyStyle->error($errors);

            return false;
        }

        $symfonyStyle->success((string)__('XML is valid.'));

        return true;
    }
}
