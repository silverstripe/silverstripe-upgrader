<?php

namespace SilverStripe\Upgrader\Console;

use InvalidArgumentException;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameTranslateKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\RenameYMLLangKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\UpdateConfigClasses;
use SilverStripe\Upgrader\UpgradeRule\SS\RenameTemplateLangKeys;
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
    use FileCommandTrait;

    protected function configure()
    {
        $this->setName('upgrade')
            ->setDescription('Upgrade a set of code files to work with a newer version of a library ')
            ->setDefinition([
                $this->getPathInputArgument(),
                new InputOption(
                    'rule',
                    'r',
                    InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                    "List of rules to run (specify --rule=* for each rule).\n"
                    . "<comment> [allowed: [\"code\",\"config\",\"lang\"]]</comment>\n",
                    ['code', 'config']
                ),
                $this->getRootInputOption(),
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
        // Build spec
        $spec = new UpgradeSpec();
        $rules = $this->getRules($input);
        $config = $this->getConfig($input);
        foreach ($rules as $rule) {
            $spec->addRule($rule->withParameters($config));
        }

        // Create upgrader with this spec
        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Load the code to be upgraded and run the upgrade process
        $filePath = $this->getFilePath($input);
        $output->writeln("Running upgrades on \"{$filePath}\"");
        $exclusions = isset($config['excludedPaths']) ? $config['excludedPaths'] : [];
        $code = new DiskCollection($filePath, true, $exclusions);
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
     * @param InputInterface $input
     * @return array
     */
    protected function getRules($input): array
    {
        $rules = $input->getOption('rule');
        $allowed = ['code', 'config', 'lang'];
        $invalid = array_diff($rules, $allowed);
        if ($invalid) {
            throw new InvalidArgumentException("Invalid --rule option(s): " . implode(',', $invalid));
        }
        if (empty($rules)) {
            throw new InvalidArgumentException("At least one --rule is necessary");
        }
        // Build rules for this set of upgrades
        $ruleObjects = [];
        if (in_array('code', $rules)) {
            $ruleObjects[] = new RenameClasses();
        }
        if (in_array('config', $rules)) {
            $ruleObjects[] = new UpdateConfigClasses();
        }
        if (in_array('lang', $rules)) {
            $ruleObjects[] = new RenameTranslateKeys();
            $ruleObjects[] = new RenameYMLLangKeys();
            $ruleObjects[] = new RenameTemplateLangKeys();
        }
        return $ruleObjects;
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    protected function getConfig($input): array
    {
        $rootPath = $this->getRootPath($input);
        $config = ConfigFile::loadCombinedConfig($rootPath);
        if (!$config) {
            throw new InvalidArgumentException(
                "No .upgrade.yml definitions found in modules on \"{$rootPath}\". " .
                "Please ensure you upgrade your SilverStripe dependencies before running this task."
            );
        }
        return $config;
    }
}
