<?php

declare(strict_types=1);

namespace ImaginationMedia\XmlValidator\Test\Integration\_stubs\Setup\Console;

use Symfony\Component\Console\Command\Command;

class CommandList extends \Magento\Setup\Console\CommandList
{
    /**
     * @return class-string<Command>[]
     */
    public function getCommands(): array
    {
        return $this->getCommandsClasses();
    }
}
