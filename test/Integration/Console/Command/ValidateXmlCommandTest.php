<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Test\Integration\Console\Command;

use ImaginationMedia\XmlValidator\Console\Command\ValidateXmlCommand;
use Magento\Framework\Console\CommandListInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Composite;
use Magento\Framework\Phrase\Renderer\MessageFormatter;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Framework\Translate;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Tester\CommandTester;

use function preg_replace;

final class ValidateXmlCommandTest extends TestCase
{
    public function testCommandIsRegistered(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $commands = $objectManager->get(CommandListInterface::class)->getCommands();

        self::assertArrayHasKey('imaginationmedia_xmlvalidator_validate_xml_command', $commands);
        self::assertInstanceOf(
            ValidateXmlCommand::class,
            $commands['imaginationmedia_xmlvalidator_validate_xml_command']
        );
    }

    public function testCommandIsConfigured(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $validateXmlCommand = $objectManager->create(ValidateXmlCommand::class);
        $expectedArguments = [
            'paths' => new InputArgument(
                'paths',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'List of files or directories to validate'
            )
        ];

        self::assertSame(ValidateXmlCommand::COMMAND_NAME, $validateXmlCommand->getName());
        self::assertSame('Validates an XML file against its configured schema', $validateXmlCommand->getDescription());
        self::assertEquals($expectedArguments, $validateXmlCommand->getDefinition()->getArguments());
    }

    /**
     * @dataProvider validatesXmlDataProvider
     * @param array{paths: string[]} $commandOptions
     */
    public function testCommandValidatesXml(
        array $commandOptions,
        string $expectedOutput,
        int $expectedReturnCode
    ): void {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ValidateXmlCommand $validateXmlCommand */
        $validateXmlCommand = $objectManager->create(ValidateXmlCommand::class);
        /** @var CommandTester $commandTester */
        $commandTester = $objectManager->create(
            CommandTester::class,
            [
                'command' => $validateXmlCommand
            ]
        );
        $translateMock = $this->getMockBuilder(Translate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLocale'])
            ->getMock();
        /** @var RendererInterface $messageFormatter */
        $messageFormatter = $objectManager->create(
            MessageFormatter::class,
            [
                'translate' => $translateMock
            ]
        );
        /** @var RendererInterface $renderer */
        $renderer = $objectManager->create(
            Composite::class,
            [
                'renderers' => [
                    $messageFormatter,
                    $objectManager->create(Placeholder::class)
                ]
            ]
        );

        $translateMock->method('getLocale')
            ->willReturn('en_US');

        Phrase::setRenderer($renderer);

        $commandTester->execute($commandOptions);

        $actualOutput = preg_replace('/\h+$/m', '', $commandTester->getDisplay());
        $actualReturnCode = $commandTester->getStatusCode();

        self::assertSame($expectedOutput, $actualOutput);
        self::assertSame($expectedReturnCode, $actualReturnCode);
    }

    /**
     * @return array<string, array<string, array<string, string[]>|string|int>>
     */
    public function validatesXmlDataProvider(): array
    {
        $paths = [
            'valid_module_xml' => __DIR__ . '/../../_files/valid/module.xml',
            'invalid_module_xml' => __DIR__ . '/../../_files/invalid/module.xml',
            'excluded_phpunit_xml' => 'phpunit.xml',
            'excluded_phpunit_xml_full' => __DIR__ . '/../../_files/invalid/phpunit.xml',
        ];

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

                 Validating {$paths['valid_module_xml']} against Module/etc/module.xsd...

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

                 Validating {$paths['invalid_module_xml']} against Module/etc/module.xsd...

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

                 Validating {$paths['valid_module_xml']} against Module/etc/module.xsd...

                 [OK] XML is valid.

                 Validating {$paths['invalid_module_xml']} against Module/etc/module.xsd...

                 [ERROR] Invalid XML. Errors:

                         Line 4: Element 'module': This element is not expected.


                1 of 2 files are valid

                OUTPUT,
                'expectedReturnCode' => 1
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
}
