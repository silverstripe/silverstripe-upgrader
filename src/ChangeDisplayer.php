<?php

namespace SilverStripe\Upgrader;

use Diff_Renderer_Text_Unified;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Displays the changes and other information provided in a CodeChangeSet to CLI.
 */
class ChangeDisplayer
{

    /**
     * Render a visual representation of a Code Change Set to an output.
     * @param OutputInterface $output
     * @param CodeChangeSet $changes
     * @return void
     */
    public function displayChanges(OutputInterface $output, CodeChangeSet $changes): void
    {
        foreach ($changes->affectedFiles() as $path) {
            $this->displayFileOperationTitle($changes, $path, $output);
            $this->displayNewContentsForFile($changes, $path, $output);
            $this->displayWarningsForFile($changes, $path, $output);
        }
    }

    /**
     * Render a visual representation of a Code Change Set's warning to an output.
     * @param OutputInterface $output
     * @param CodeChangeSet $changes
     * @return void
     */
    public function displayWarningsOnly(OutputInterface $output, CodeChangeSet $changes): void
    {
        foreach ($changes->affectedFiles() as $path) {
            $this->displayWarningsForFile($changes, $path, $output);
        }
    }

    /**
     * Display a diff of the changes for the provided file.
     * @param CodeChangeSet $changes
     * @param string $path
     * @param OutputInterface $output
     * @return void
     */
    private function displayFileOperationTitle(CodeChangeSet $changes, string $path, OutputInterface $output): void
    {
        // Display the file title
        $ops = $changes->opsByPath($path);
        $label = $ops ?: 'unchanged';
        $display = $path . ($ops == 'renamed' ? ' -> ' . $changes->newPath($path) : '');
        $output->writeln(sprintf("%s:\t<info>%s</info>", $label, $display));
    }

    /**
     * Display a diff of the changes for the provided file.
     * @param CodeChangeSet $changes
     * @param string $path
     * @param OutputInterface $output
     * @return void
     */
    private function displayNewContentsForFile(CodeChangeSet $changes, string $path, OutputInterface $output): void
    {
        if ($changes->hasNewContents($path)) {
            // Show actual output if -v
            $new = preg_split('~\R~u', $changes->newContents($path));
            $old = preg_split('~\R~u', $changes->oldContents($path));
            $diff = new \Diff($old, $new);
            $render = $diff->render(new Diff_Renderer_Text_Unified());
            $output->writeln("<comment>{$render}</comment>");
        }
    }

    /**
     * Display warnings for the provided file, if any.
     *
     * @param CodeChangeSet $changes
     * @param string $path
     * @param OutputInterface $output
     * @return void
     */
    private function displayWarningsForFile(CodeChangeSet $changes, string $path, OutputInterface $output): void
    {
        // Display warnings if any
        if ($changes->hasWarnings($path)) {
            $output->writeln("Warnings for $path:");
            foreach ($changes->warningsForPath($path) as $warning) {
                $output->writeln(" - $warning");
            }
        }
    }
}
