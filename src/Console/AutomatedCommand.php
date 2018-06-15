<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface to apply to the a Command if we want to run it in _automated_ mode via the `CommandRunner`.
 */
interface AutomatedCommand
{

    /**
     * Run this command in automated mode.
     * @param array $args Minimal arguments this command needs to run automated.
     * @param OutputInterface $output
     * @return int
     */
    public function automatedRun(array $args, OutputInterface $output): int;

    /**
     * A Code Change Set generate by running the command, if any.
     * @return CodeChangeSet
     */
    public function getDiff(): CodeChangeSet;

    /**
     * Build the updated argument list following the execution of the command. This should be used by command that
     * alter the underlying state of the project in a way that might affect how future command will run.
     * @return array
     */
    public function updatedArguments(): array;

    /**
     * Flag that can be read during the normal execution of the command to alter the behavior of the command.
     * @return bool
     */
    public function isAutomated(): bool;
}
