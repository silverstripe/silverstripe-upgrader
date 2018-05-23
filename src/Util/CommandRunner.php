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
        $args = $this->addProjectFolders($args);

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
}
