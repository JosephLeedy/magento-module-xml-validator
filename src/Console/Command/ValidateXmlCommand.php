<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateXmlCommand extends Command
{
    public const COMMAND_NAME = 'dev:xml:validate';

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Validates an XML file against its configured schema');
        $this->addArgument(
            'files',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'List of files or directories to validate'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
    }
}
