<?php

namespace SilverStripe\Upgrader\Tests\Console;

use SilverStripe\Upgrader\Console\AbstractCommand;
use SilverStripe\Upgrader\Console\AutomatedCommand;
use SilverStripe\Upgrader\Console\AutomatedCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * This is a mock command.
 *
 * After this command has run:
 * * $this->state['execute'] should be true ;
 * * $this->state['enrichArgs'] should be true ;
 * * $this->updatedArguments() should return an array with an incremented --exec key
 *
 */
class MockFailedAutomatedCommand extends AbstractCommand implements AutomatedCommand
{
    use AutomatedCommandTrait;

    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
        parent::__construct(null);
    }

    protected function configure()
    {
        $this->setName($this->name)
            ->setDefinition([
                new InputOption(
                    'exec',
                    'X',
                    InputOption::VALUE_OPTIONAL,
                    'Prefer ~ to ^ avoid accidental updates'
                )
            ]);
    }

    public function execute(InputInterface $in, OutputInterface $output)
    {
        throw new RuntimeException('The shit has hit the fan.');
    }

    protected function enrichArgs(array $arg): array
    {
        return $arg;
    }
}
