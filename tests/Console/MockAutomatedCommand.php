<?php

namespace SilverStripe\Upgrader\Tests\Console;

use SilverStripe\Upgrader\Console\AbstractCommand;
use SilverStripe\Upgrader\Console\AutomatedCommand;
use SilverStripe\Upgrader\Console\AutomatedCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * This is a mock command.
 *
 * After this command has run:
 * * $this->state['execute'] should be true ;
 * * $this->state['enrichArgs'] should be true ;
 * * $this->updatedArguments() should return an array with an incremented --exec key
 *
 */
class MockAutomatedCommand extends AbstractCommand implements AutomatedCommand
{
    use AutomatedCommandTrait;

    private $name;

    /**
     * Tells you if the execute or enrichArgs methods have been call.
     * @var array
     */
    public $state = [
        'execute' => false,
        'enrichArgs' => false,
    ];

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
        $this->state['execute'] = true;
        $this->args['--exec'] = $in->getOption('exec');
    }

    protected function enrichArgs(array $arg): array
    {
        $this->state['enrichArgs'] = true;

        if (isset($arg['--exec'])) {
            $arg['--exec']++;
        } else {
            $arg['--exec'] = 1;
        }

        return $arg;
    }
}
