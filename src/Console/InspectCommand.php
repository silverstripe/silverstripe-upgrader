<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameTranslateKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\RenameYMLLangKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\UpdateConfigClasses;
use SilverStripe\Upgrader\UpgradeRule\SS\RenameTemplateLangKeys;
use SilverStripe\Upgrader\Util\ConfigFile;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeSpec;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\ChangeDisplayer;

class InspectCommand extends AbstractCommand
{

    protected function configure()
    {
        $this->setName('inspect')
            ->setDescription('Inspect unfixable code and provide useful warnings. Run after "upgrade" command.')
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
                )
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());
        $filePath = $this->realPath($settings['path']);
        $rootPath = $this->realPath($settings['root-dir']);

        // Sanity check input
        if (!is_dir($rootPath)) {
            $rootPath = $settings['root-dir'];
            throw new \InvalidArgumentException("No silverstripe project found in root-dir \"{$rootPath}\"");
        }
        if (!file_exists($filePath)) {
            $filePath = $settings['path'];
            throw new \InvalidArgumentException("path \"{$filePath}\" specified doesn't exist");
        }

        // Load the upgrade spec
        $config = ConfigFile::loadCombinedConfig($rootPath);
        $spec = new UpgradeSpec();
        $spec->addRule((new ApiChangeWarningsRule())->withParameters($config));

        // Create upgrader with this spec
        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Load the code to be upgraded and run the upgrade process
        $output->writeln("Running inspections on \"{$filePath}\"");
        $exclusions = isset($config['excludedPaths']) ? $config['excludedPaths'] : [];
        $code = new DiskCollection($filePath, true, $exclusions);

        // Run upgrader, but discard any changes: Only show inspection warnings
        $changes = $upgrader->upgrade($code);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);
        $count = count($changes->allChanges());
    }
}
