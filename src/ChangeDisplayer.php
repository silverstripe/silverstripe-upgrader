<?php

namespace SilverStripe\Upgrader;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Displays the changes and other information provided in a CodeChangeSet to CLI
 */
class ChangeDisplayer
{

    public function displayChanges(OutputInterface $output, CodeChangeSet $changes)
    {
        foreach ($changes->affectedFiles() as $path) {
            if ($changes->hasNewContents($path)) {
                $output->writeln("New contents for $path:");
//                $output->writeln($changes->newContents($path));
//                $output->writeln("------");
            }

            if ($changes->hasWarnings($path)) {
                $output->writeln("Warnings for $path:");
                foreach ($changes->warningsForPath($path) as $warning) {
                    $output->writeline(" - $warning");
                }
                $output->writeln("------");
            }
        }
    }
}
