<?php

namespace SilverStripe\Upgrader\Util;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Console\AbstractCommand;
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
    public function run(
        Application $app,
        array $commands,
        array $args,
        OutputInterface $output
    ): void {
        $args = $this->addProjectFolders($args);

        $step = 0;

        $warnings = new CodeChangeSet();

        // Run each command one by one.
        foreach ($commands as $commandName) {
            $step++;

            /**
             * @var AutomatedCommand|AbstractCommand
             */
            $cmd = $app->find($commandName);
            if ($cmd instanceof AutomatedCommand) {
                $titleBlock = sprintf('Step %s - Running %s', $step, $cmd->getName());

                $output->write($this->wrapTitle($titleBlock));
                $cmd->automatedRun($args, $output);
                $args = $cmd->updatedArguments();
                $diff = $cmd->getDiff();
                if ($diff) {
                    $warnings->mergeWarnings($diff);
                }
            } else {
                throw new LogicException(sprintf(
                    '%s does not implement the AutomatedCommand interface.',
                    $commandName
                ));
            }
        }

        // Show a summary of the upgrade
        $output->write($this->wrapTitle('Summary of upgrades'));

        $output->writeln('All commands got executed successfully. Please address any outstanding warnings.');

        $displayer = new ChangeDisplayer();
        $displayer->displayWarningsOnly($output, $warnings);
    }

    /**
     * Add a `project-path` key and `code-path` key to the arguments. Commands who requires a `path` argument can pick
     * one of those two values.
     *
     * @internal This is necessary because some commands expect to be run on the `mysite/code` folder, while others
     * expect to be run on `mysite`.
     * @param array $args
     * @return array
     */
    public function addProjectFolders(array $args): array
    {
        if (isset($args['--root-dir'])) {
            // Try to find a project folder
            foreach (['mysite', 'app'] as $folder) {
                $projectPath = $args['--root-dir'] . DIRECTORY_SEPARATOR . $folder;

                if (file_exists($projectPath) && is_dir($projectPath)) {
                    $args['project-path'] = $projectPath;
                    break;
                }
            }

            // Try to find a project path
            if (isset($args['project-path'])) {
                foreach (['code', 'src'] as $folder) {
                    $codePath = $args['project-path'] . DIRECTORY_SEPARATOR . $folder;

                    if (file_exists($codePath) && is_dir($codePath)) {
                        $args['code-path'] = $codePath;
                        break;
                    }
                }
            }
        }

        return $args;
    }


    /**
     * Wrap the title with a lot of emphasis to make it stand out.
     * @param string $title
     * @return string
     */
    private function wrapTitle(string $title): string
    {
        $lineLength = 80;
        $textLength = $lineLength - 6;

        $string = "\n\n<fg=cyan;options=bold>";
        $string .= str_repeat('*', $lineLength) . "\n";
        $string .= '*  ' . str_repeat(' ', $textLength) . "  *\n";
        $string .= '*  ' . strtoupper($title) . str_repeat(' ', $textLength - strlen($title)) . "  *\n";
        $string .= '*  ' . str_repeat(' ', $textLength) . "  *\n";
        $string .= str_repeat('*', $lineLength) . "</>\n";

        return $string;
    }
}
