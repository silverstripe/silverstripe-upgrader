<?php

namespace SilverStripe\Upgrader\Util;

use SilverStripe\Upgrader\Console\AutomatedCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Aggregate many commands together and run them sequentially.
 */
class CommandRunner
{

    /**
     * Run a list of command sequentially.
     * @param Application $app
     * @param array $commands
     * @param array $args
     * @param OutputInterface $output
     */
    public function run(Application $app, array $commands, array $args, OutputInterface $output): void
    {
        foreach ($commands as $commandName) {
            /**
             * @var AutomatedCommand
             */
            $cmd = $app->find($commandName);
            if ($cmd instanceof AutomatedCommand) {
                $cmd->automatedRun($args, $output);
                $args = $cmd->updatedArguments();
            } else {
                throw new LogicException(sprintf(
                    '%s does not implement the AutomatedCommand interface.',
                    $commandName
                ));
            }
        }
    }
}
