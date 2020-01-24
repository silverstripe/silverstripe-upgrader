<?php

namespace SilverStripe\Upgrader\Console;

use BadMethodCallException;
use SilverStripe\Upgrader\Util\ConfigFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DoctorCommand extends AbstractCommand
{
    use FileCommandTrait;

    protected function configure()
    {
        $this->setName('doctor')
            ->setDescription('Run all cleanup tasks configured for this project')
            ->setDefinition([
                $this->getRootInputOption()
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console = new SymfonyStyle($input, $output);

        $rootPath = $this->getRootPath($input);

        // Load the code to be upgraded and run the upgrade process

        $config = ConfigFile::loadCombinedConfig($rootPath);
        $tasks = isset($config['doctorTasks']) ? $config['doctorTasks']: [];
        if (empty($tasks)) {
            $console->text("No tasks configured for this installation.");
            return;
        }

        $count = count($tasks);

        $console->section("Running {$count} doctor tasks on \"{$rootPath}\"");
        $console->listing(array_keys($tasks));

        $console->warning(
            'These cleanup tasks are arbitrary PHP code snippets that ship with individual modules. They are not ' .
            'part of the SilverStripe upgrader and may have destructive side-affects. Make sure you understand ' .
            'what each task is meant to do and back up your changes before continuing.' . "\n\n" .
            'DO NOT PROCEED IF YOU ARE UNSURE.'
        );

        if (!$console->confirm('Do you want to run the cleanup tasks?', false)) {
            return;
        };


        foreach ($tasks as $class => $path) {
            if (!file_exists($path)) {
                $relative = substr($path, strlen($rootPath));
                throw new BadMethodCallException("No task in {$relative} found");
            }
            require_once $path;
            $task = new $class;
            if (!is_callable($task)) {
                throw new BadMethodCallException(
                    "Class {$class} could not be invoked. Perhaps a missing __invoke() method?"
                );
            }
            // Invoke
            $console->title("Running task <info>{$class}</info>...");
            $task($input, $output, $rootPath);
        }

        $console->success("All tasks run");
        return null;
    }
}
