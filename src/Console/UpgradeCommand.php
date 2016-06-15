<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\UpgradeRule\RenameClasses;
use SilverStripe\Upgrader\Util\ConfigFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeSpec;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\ChangeDisplayer;

class UpgradeCommand extends AbstractCommand
{

    protected function configure()
    {
        $this->setName('upgrade')
            ->setDescription('Upgrade a set of code files to work with a newer version of a library ')
            ->setDefinition([
                new InputArgument(
                    'path',
                    InputArgument::OPTIONAL,
                    'The root path to your code needing to be upgraded. Defaults to current directory.',
                    '.'
                ),
                new InputOption(
                    'root-dir',
                    'd',
                    InputOption::VALUE_REQUIRED,
                    'Specify project root dir, if not the current directory',
                    '.'
                ),
                new InputOption(
                    'write',
                    'w',
                    InputOption::VALUE_NONE,
                    'Actually write the changes (to disk and to upgrade-spec), rather than merely displaying them'
                )
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());
        $filePath = $this->realPath($settings['path']);
        $rootPath = $this->realPath($settings['root-dir']);
        $writeChanges = !empty($settings['write']);

        // Sanity check input
        if (!is_dir($rootPath)) {
            $rootPath = $settings['root-dir'];
            throw new \InvalidArgumentException("No silverstripe project found in root-dir \"{$rootPath}\"");
        }
        if (!file_exists($filePath)) {
            $filePath = $settings['path'];
            throw new \InvalidArgumentException("path \"{$filePath}\" specified doesn't exist");
        }
        // Find module name
        if (stripos($filePath, $rootPath) !== 0) {
            throw new \InvalidArgumentException(
                "root-dir \"{$rootPath}\" is not a parent of the specified path \"{$filePath}\""
            );
        }

        // Load the upgrade spec and create an upgrader
        $config = ConfigFile::loadCombinedConfig($rootPath);
        $spec = new UpgradeSpec([
            (new RenameClasses())->withParameters($config)
        ]);

        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Load the code to be upgraded and run the upgrade process
        $output->writeln("Running upgrades on \"{$filePath}\"");
        $code = new DiskCollection($filePath, true);
        $changes = $upgrader->upgrade($code);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);
        $count = count($changes->allChanges());

        // Apply them to the project
        if ($writeChanges) {
            $output->writeln("Writing changes for {$count} files");
            $code->applyChanges($changes);
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }

    }
}
