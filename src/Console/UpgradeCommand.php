<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\UpgradeRule\JS\RenameJSLangKeys;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameTranslateKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\RenameYMLLangKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\UpdateConfigClasses;
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
                    'rule',
                    'r',
                    InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                    "List of rules to run (specify --rule=* for each rule).\n"
                    . "<comment> [allowed: [\"code\",\"config\",\"lang\"]]</comment>\n",
                    ['code', 'config']
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
        $rules = $settings['rule'];

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

        // Validate rules
        $allowed = ['code', 'config', 'lang'];
        $invalid = array_diff($rules, $allowed);
        if ($invalid) {
            throw new \InvalidArgumentException("Invalid --rule option(s): " . implode(',', $invalid));
        }
        if (empty($rules)) {
            throw new \InvalidArgumentException("At least one --rule is necessary");
        }

        // Load the upgrade spec
        $config = ConfigFile::loadCombinedConfig($rootPath);
        $spec = new UpgradeSpec();
        if (in_array('code', $rules)) {
            $spec->addRule((new RenameClasses())->withParameters($config));
        }
        if (in_array('config', $rules)) {
            $spec->addRule((new UpdateConfigClasses())->withParameters($config));
        }
        if (in_array('lang', $rules)) {
            $spec->addRule((new RenameTranslateKeys())->withParameters($config));
            $spec->addRule((new RenameYMLLangKeys())->withParameters($config));
            $spec->addRule((new RenameJSLangKeys())->withParameters($config));
        }

        // Create upgrader with this spec
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
