<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Test\Integration\Console\Command;

use ImaginationMedia\XmlValidator\Console\Command\ValidateXmlCommand;
use Magento\Framework\Console\CommandListInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;

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
            'files' => new InputArgument(
                'files',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'List of files or directories to validate'
            )
        ];

        self::assertSame(ValidateXmlCommand::COMMAND_NAME, $validateXmlCommand->getName());
        self::assertSame('Validates an XML file against its configured schema', $validateXmlCommand->getDescription());
        self::assertEquals($expectedArguments, $validateXmlCommand->getDefinition()->getArguments());
    }
}
