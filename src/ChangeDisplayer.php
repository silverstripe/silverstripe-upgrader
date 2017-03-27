<?php

namespace SilverStripe\Upgrader;

use Diff_Renderer_Text_Unified;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
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
                $output->writeln("New contents for <info>$path</info>");

                // Show actual output if -v
                $new = preg_split('~\R~u', $changes->newContents($path));
                $old = preg_split('~\R~u', $changes->oldContents($path));
                $diff = new \Diff($old, $new);
                $render = $diff->render(new Diff_Renderer_Text_Unified());
                $output->writeln("<comment>{$render}</comment>");
            }

            if ($changes->hasWarnings($path)) {
                $output->writeln("Warnings for $path:");
                foreach ($changes->warningsForPath($path) as $warning) {
                    $output->writeln(" - $warning");
                }
            }
        }
    }
}
