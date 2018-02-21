<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Autoload\CollectionAutoloader;
use SilverStripe\Upgrader\Autoload\IncludedProjectAutoloader;
use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule;
use SilverStripe\Upgrader\UpgradeSpec;
use SilverStripe\Upgrader\Util\PHPStanState;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends UpgradeCommand
{
    protected function configure()
    {
        $this->setName('inspect')
            ->setDescription('Runs additional post-upgrade inspections, warnings, and rewrites to tidy up loose ends')
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
        // Setup PHPStan
        $state = new PHPStanState();
        $state->init();
        $container = $state->getContainer();

        // Post-upgrade requires additional composer autoload.php to work
        $this->enableProjectAutoloading($input);

        // Build spec
        $spec = new UpgradeSpec();
        $config = $this->getConfig($input);
        $spec->addRule((new ApiChangeWarningsRule($container))->withParameters($config));

        // Create upgrader with this spec
        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Build disc collection
        $filePath = $this->getFilePath($input);
        $exclusions = isset($config['excludedPaths']) ? $config['excludedPaths'] : [];
        $code = new DiskCollection($filePath, true, $exclusions);

        // Run upgrade
        $output->writeln("Running post-upgrade on \"{$filePath}\"");
        $changes = $upgrader->upgrade($code);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);
        $count = count($changes->allChanges());

        // Apply them to the project
        if ($input->getOption('write')) {
            $output->writeln("Writing changes for {$count} files");
            $code->applyChanges($changes);
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }
    }

    /**
     * Setup autoloading that loads project files
     *
     * @param InputInterface $input
     */
    protected function enableProjectAutoloading(InputInterface $input): void
    {
        // Setup base autoloading (psr-4 should cover this)
        $base = $this->getRootPath($input);
        $projectLoader = new IncludedProjectAutoloader($base);
        $projectLoader->register();

        // Setup custom autoloading for the given upgrade path
        $filePath = $this->getFilePath($input);
        $codeBase = new DiskCollection($filePath);
        $collectionLoader = new CollectionAutoloader();
        $collectionLoader->addCollection($codeBase);
        $collectionLoader->register();
    }
}
