<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Test\Integration\Console\Command;

use ImaginationMedia\XmlValidator\Console\Command\ValidateXmlCommand;
use ImaginationMedia\XmlValidator\Test\Integration\_stubs\Setup\Console\CommandList;
use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\DomDocument\DomDocumentFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Composite;
use Magento\Framework\Phrase\Renderer\MessageFormatter;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Framework\Translate;
use Magento\Setup\Model\ObjectManagerProvider;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Tester\CommandTester;

use function ltrim;
use function preg_replace;
use function putenv;
use function realpath;
use function str_replace;

use const BP;

final class ValidateXmlCommandTest extends TestCase
{
    private ObjectManagerInterface $objectManager;
    private ValidateXmlCommand $validateXmlCommand;

    public function testCommandIsRegistered(): void
    {
        $commands = $this->objectManager->get(CommandList::class)->getCommands();

        self::assertContains(ValidateXmlCommand::class, $commands);
    }

    public function testCommandIsConfigured(): void
    {
        $expectedArguments = [
            'paths' => new InputArgument(
                'paths',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'List of files or directories to validate'
            )
        ];

        self::assertSame(ValidateXmlCommand::COMMAND_NAME, $this->validateXmlCommand->getName());
        self::assertSame(
            'Validates an XML file against its configured schema',
            $this->validateXmlCommand->getDescription()
        );
        self::assertEquals($expectedArguments, $this->validateXmlCommand->getDefinition()->getArguments());
    }

    /**
     * @dataProvider validatesXmlAndOutputsToConsoleDataProvider
     * @param array{paths: string[]} $commandOptions
     */
    public function testCommandValidatesXmlAndOutputsToConsole(
        array $commandOptions,
        string $expectedOutput,
        int $expectedReturnCode
    ): void {
        $this->validateXmlCommand = $this->objectManager->create(ValidateXmlCommand::class);
        /** @var CommandTester $commandTester */
        $commandTester = $this->objectManager->create(
            CommandTester::class,
            [
                'command' => $this->validateXmlCommand
            ]
        );

        $commandTester->execute($commandOptions);

        $actualOutput = preg_replace('/\h+$/m', '', $commandTester->getDisplay());
        $actualReturnCode = $commandTester->getStatusCode();

        self::assertSame($expectedOutput, $actualOutput);
        self::assertSame($expectedReturnCode, $actualReturnCode);
    }

    public function testCommandValidatesXmlAndOutputsToGitHubActions(): void
    {
        /** @var CommandTester $commandTester */
        $commandTester = $this->objectManager->create(
            CommandTester::class,
            [
                'command' => $this->validateXmlCommand
            ]
        );
        $commandOptions = [
            'paths' => [
                realpath(__DIR__ . '/../../_files/invalid/module.xml') ?: '',
            ]
        ];

        putenv('GITHUB_ACTIONS=true');

        $commandTester->execute($commandOptions);

        $expectedOutput = <<<OUTPUT
        ::error file=app/code/ImaginationMedia/XmlValidator/Test/Integration/_files/invalid/module.xml,line=4,col=0::Element 'module': This element is not expected.%0A

        OUTPUT;
        $expectedReturnCode = 1;
        $actualOutput = preg_replace('/\h+$/m', '', $commandTester->getDisplay());
        $actualReturnCode = $commandTester->getStatusCode();

        self::assertSame($expectedOutput, $actualOutput);
        self::assertSame($expectedReturnCode, $actualReturnCode);
    }

    /**
     * @return array<string, array<string, array<string, string[]>|string|int>>
     */
    public function validatesXmlAndOutputsToConsoleDataProvider(): array
    {
        $paths = [
            'valid_module_xml' => __DIR__ . '/../../_files/valid/module.xml',
            'valid_config_xml' => __DIR__ . '/../../_files/valid/config.xml',
            'invalid_module_xml' => __DIR__ . '/../../_files/invalid/module.xml',
            'excluded_phpunit_xml' => 'phpunit.xml',
            'excluded_phpunit_xml_full' => __DIR__ . '/../../_files/invalid/phpunit.xml',
        ];
        $relativePaths = [];

        foreach ($paths as $name => $path) {
            $relativePaths[$name] = ltrim(str_replace(BP, '', $path), '\\/');
        }

        return [
            'valid module.xml' => [
                'commandOptions' => [
                    'paths' => [
                        $paths['valid_module_xml']
                    ]
                ],
                'expectedOutput' => <<<OUTPUT

                Imagination Media XML Validator
                ===============================

                 Validating {$relativePaths['valid_module_xml']} against vendor/magento/framework/Module/etc/module.xsd...

                 [OK] XML is valid.

                1 of 1 file is valid

                OUTPUT,
                'expectedReturnCode' => 0
            ],
            'invalid module.xml' => [
                'commandOptions' => [
                    'paths' => [
                        $paths['invalid_module_xml']
                    ]
                ],
                'expectedOutput' => <<<OUTPUT

                Imagination Media XML Validator
                ===============================

                 Validating {$relativePaths['invalid_module_xml']} against vendor/magento/framework/Module/etc/module.xsd...

                 [ERROR] Invalid XML. Errors:

                         Line 4: Element 'module': This element is not expected.


                0 of 1 file is valid

                OUTPUT,
                'expectedReturnCode' => 1
            ],
            'valid and invalid module.xml' => [
                'commandOptions' => [
                    'paths' => [
                        $paths['valid_module_xml'],
                        $paths['invalid_module_xml']
                    ]
                ],
                'expectedOutput' => <<<OUTPUT

                Imagination Media XML Validator
                ===============================

                 Validating {$relativePaths['valid_module_xml']} against vendor/magento/framework/Module/etc/module.xsd...

                 [OK] XML is valid.

                 Validating {$relativePaths['invalid_module_xml']} against vendor/magento/framework/Module/etc/module.xsd...

                 [ERROR] Invalid XML. Errors:

                         Line 4: Element 'module': This element is not expected.


                1 of 2 files are valid

                OUTPUT,
                'expectedReturnCode' => 1
            ],
            'missing Magento schema declaration in config.xml' => [
                'commandOptions' => [
                    'paths' => [
                        $paths['valid_config_xml']
                    ]
                ],
                'expectedOutput' => <<<OUTPUT

                Imagination Media XML Validator
                ===============================

                 [WARNING] XML file
                           "{$relativePaths['valid_config_xml']}" does
                           not have a Magento schema defined

                1 of 1 file is valid

                OUTPUT,
                'expectedReturnCode' => 0
            ],
            'non-Magento XML file' => [
                'commandOptions' => [
                    'paths' => [
                        $paths['excluded_phpunit_xml_full']
                    ]
                ],
                'expectedOutput' => <<<OUTPUT

                Imagination Media XML Validator
                ===============================

                 [WARNING] File "{$paths['excluded_phpunit_xml']}" is not a Magento XML file.

                0 of 0 files are valid

                OUTPUT,
                'expectedReturnCode' => 0
            ],
        ];
    }

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        $objectManagerProviderMock = $this->createMock(ObjectManagerProvider::class);

        $objectManagerMock->method('create')
            ->willReturnMap(
                [
                    [
                        DomDocumentFactory::class,
                        [],
                        new DomDocumentFactory()
                    ],
                    [
                        File::class,
                        [],
                        new File()
                    ],
                    [
                        UrnResolver::class,
                        [],
                        new UrnResolver()
                    ]
                ]
            );

        $objectManagerProviderMock
            ->method('get')
            ->willReturn($objectManagerMock);

        $this->objectManager->configure(
            [
                ObjectManagerProvider::class => [
                    'shared' => true
                ],
            ]
        );
        $this->objectManager->addSharedInstance($objectManagerProviderMock, ObjectManagerProvider::class);

        $this->fixTranslationRenderer();

        $this->validateXmlCommand = $this->objectManager->create(
            ValidateXmlCommand::class,
            [
                'objectManagerProvider' => $objectManagerProviderMock
            ]
        );
    }

    protected function tearDown(): void
    {
        unset($this->objectManager, $this->validateXmlCommand);
    }

    private function fixTranslationRenderer(): void
    {
        $translateMock = $this->getMockBuilder(Translate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLocale'])
            ->getMock();
        /** @var RendererInterface $messageFormatter */
        $messageFormatter = $this->objectManager->create(
            MessageFormatter::class,
            [
                'translate' => $translateMock
            ]
        );
        /** @var RendererInterface $renderer */
        $renderer = $this->objectManager->create(
            Composite::class,
            [
                'renderers' => [
                    $messageFormatter,
                    $this->objectManager->create(Placeholder::class)
                ]
            ]
        );

        $translateMock->method('getLocale')
            ->willReturn('en_US');

        Phrase::setRenderer($renderer);
    }
}
