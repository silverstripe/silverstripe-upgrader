<?php

namespace SilverStripe\Upgrader\Console;

use InvalidArgumentException;
use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeRule\PHP\ClassToTraitRule;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameTranslateKeys;
use SilverStripe\Upgrader\UpgradeRule\PHP\UpgradePhpUnitTests;
use SilverStripe\Upgrader\UpgradeRule\SS\RenameTemplateLangKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\RenameYMLLangKeys;
use SilverStripe\Upgrader\UpgradeRule\YML\UpdateConfigClasses;
use SilverStripe\Upgrader\UpgradeSpec;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeCommand extends AbstractCommand implements AutomatedCommand
{
    use FileCommandTrait;
    use AutomatedCommandTrait;
    use ConfigurableCommandTrait;

    protected function configure()
    {
        $this->setName('upgrade')
            ->setDescription('Upgrade a set of code files to work with a newer version of a library.')
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
                new InputOption(
                    'prompt',
                    'p',
                    InputOption::VALUE_NONE,
                    "Show a prompt before making ambiguous class replacements."
                ),
                $this->getRootInputOption(),
                $this->getWriteInputOption()
            ]);
    }

    /**
     * @inheritdoc
     * @param array $args
     * @return array
     */
    protected function enrichArgs(array $args): array
    {
        $args['--write'] = true;
        $args['path'] = $args['project-path'];
        return array_intersect_key(
            $args,
            array_flip([
                '--write',
                '--root-dir',
                'path',
            ])
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Build spec
        $spec = new UpgradeSpec();
        $rules = $this->getRules($input, $output);
        $rootPath = $this->getRootPath($input);
        $config = $this->getConfig($rootPath);
        foreach ($rules as $rule) {
            $spec->addRule($rule->withParameters($config));
        }

        $classToTraits = isset($config['classToTraits']) ? $config['classToTraits'] : [];
        if (count($classToTraits)) {
            $spec->addRule((new ClassToTraitRule($classToTraits))->withParameters($config));
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
        $this->setDiff($changes);

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
     * @param $output
     * @param $command
     * @return array
     */
    protected function getRules($input, $output): array
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
            $ruleObjects[] = new RenameClasses($input->getOption('prompt'), $this, $input, $output);
            $ruleObjects[] = new UpgradePhpUnitTests();
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
}
