<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use SilverStripe\Upgrader\Composer\Rules;
use SilverStripe\Upgrader\Composer\Packagist;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;


use InvalidArgumentException;

/**
 * Command to try to update a composer file to use SS4.
 */
class RecomposeCommand extends AbstractCommand implements AutomatedCommand
{
    use FileCommandTrait;
    use AutomatedCommandTrait;
    use ConfigurableCommandTrait;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('recompose')
            ->setDescription('Upgrade a composer file to use the latest version of SilverStripe.')
            ->setDefinition([
                $this->getRootInputOption(),
                $this->getWriteInputOption(),
                new InputOption(
                    'strict',
                    'S',
                    InputOption::VALUE_NONE,
                    'Prefer ~ to ^ avoid accidental updates'
                ),
                new InputOption(
                    'recipe-core-constraint',
                    'R',
                    InputOption::VALUE_OPTIONAL,
                    'Version of `silverstripe/recipe-core` you are targeting. Defaults to the last stable',
                    '*'
                ),
                new InputOption(
                    'composer-path',
                    'P',
                    InputOption::VALUE_OPTIONAL,
                    'Path to the composer executable.',
                    ''
                ),
                new InputOption(
                    'quick',
                    'Q',
                    InputOption::VALUE_NONE,
                    'Terminate execution if the command in our `composer.json` file already meet the ' .
                    '`recipe-core-constraint`. This will speed up execution for scripts that need to call this ' .
                    'command repetitively.'
                )
            ]);
    }

    /**
     * @inheritdoc
     * @param array $args
     * @return array
     */
    protected function enrichArgs(array $args): array
    {
        $args['--quick'] = true;
        $args['--write'] = true;
        return array_intersect_key(
            $args,
            array_flip([
                '--quick',
                '--write',
                '--root-dir',
                '--strict',
                '--recipe-core-constraint',
                '--composer-path'
            ])
        );
    }

    /**
     * @inheritdoc
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get our input variables
        $rootPath = $this->getRootPath($input);
        $write = $input->getOption('write');

        $composerPath = $input->getOption('composer-path');
        $recipeCoreConstraint = $input->getOption('recipe-core-constraint');
        $strict = $input->getOption('strict');
        $quick = $input->getOption('quick');

        $console = new SymfonyStyle($input, $output);

        // Initialise our composer file.
        $composer = new ComposerExec($rootPath, $composerPath, $output);
        $schema = new ComposerFile($composer, $rootPath);


        // Set up some caching
        $this->initPackageCache($composer);

        // Find out what version of recipe-core we will target and if we are already using it.
        $coreTarget = $this->findTargetRecipeCore($recipeCoreConstraint);
        if ($quick && $this->recipeCoreTargetIsInstalled($composer, $coreTarget)) {
            $console->success(sprintf(
                'Project already using recipe-core %s. Nothing to do. ' .
                'Disable the `--quick` flag if you want to force the command to run.',
                $coreTarget
            ));
            return null;
        }

        // Compute Recipe equivalence from config
        $recipeEquivalences = [
            "cwp/cwp-recipe-basic" => ["cwp/cwp-recipe-cms"],
            "cwp/cwp-recipe-blog" => ["cwp/cwp-recipe-cms", "silverstripe/recipe-blog"],
            "cwp/cwp-core" => ["cwp/cwp-recipe-core"],
        ];
        $config = $this->getConfig($rootPath, false);
        if (isset($config['recipeEquivalences']) && is_array($config['recipeEquivalences'])) {
            $recipeEquivalences = array_merge(
                $recipeEquivalences,
                $config['recipeEquivalences']
            );
        }


        // Set up our rules
        $rules = [
            new Rules\PhpVersion(),
            new Rules\Rebuild($coreTarget, $console, $recipeEquivalences),
        ];
        if ($strict) {
            $rules[] = new Rules\StrictVersion();
        }

        // Try to upgrade the project
        $change = $schema->upgrade($rules, $console);
        $this->setDiff($change);

        // Check if we got new content
        $console->title('Showing difference');
        if (!$change->hasNewContents($schema->getFullPath())) {
            $console->note("Nothing to upgrade.");
            return null;
        }
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $change);

        // Ask the use if they want to save their changes.
        // This is not the standard behavior, but this command can take quite a bit of time to run.
        if (!$write) {
            $write = $console->confirm('Save changes to composer file?', false);
        }

        if ($write) {
            $schema->setContents($change->newContents($schema->getFullPath()));
            $console->note("Changes have been saved.");

            $console->title('Trying to install new dependencies');
            try {
                // We need to run the update twice because our recipes will update the `extra` object in our
                // composer.json which invalidates our composer.lock
                $composer->update('', false, true);
                $composer->update('', false, true);
                $console->success('Dependencies installed successfully.');
            } catch (RuntimeException $ex) {
                $message =
                    'Composer could not resolved your updated dependencies. '.
                    'You\'ll need to manually resolve conflicts.';
                if ($this->isAutomated()) {
                    // If we are running the command in an automated context, we need a clean failure to terminate
                    // execution.
                    throw new RuntimeException($message);
                } else {
                    $console->warning($message);
                }
            }
        } else {
            $console->note("Changes not saved; Run with --write to commit to disk");
        }


        return null;
    }

    /**
     * Get the latest version of recipe core meeting the provided constraint.
     * @param  $constraint
     * @return string
     * @throws InvalidArgumentException
     */
    protected function findTargetRecipeCore(string $constraint)
    {
        $package = new Package(SilverstripePackageInfo::RECIPE_CORE);
        $version = $package->getVersion($constraint);

        if ($version) {
            return $version->getId();
        } else {
            throw new InvalidArgumentException(
                "Could not find a version of silverstripe/recipe-core matching $constraint"
            );
        }
    }

    /**
     * Initialise the Packagist cache.
     * @param ComposerExec $composer
     */
    protected function initPackageCache(ComposerExec $composer)
    {
        $mainCache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'silverstripe-upgrader-cache';
        if (!file_exists($mainCache)) {
            mkdir($mainCache);
        }
        Packagist::addCacheFolder($mainCache);

        $composerCache = $composer->getCacheDir();
        if ($composerCache) {
            $composerCache .= DIRECTORY_SEPARATOR . 'repo' . DIRECTORY_SEPARATOR . 'https---packagist.org';
            if (file_exists($composerCache)) {
                Packagist::addCacheFolder($composerCache);
            }
        }
    }

    /**
     * Determine if the current project has the required version of recipe core already installed.
     * @param ComposerExec $composer
     * @param string $targetRecipeCore
     * @return bool
     */
    protected function recipeCoreTargetIsInstalled(ComposerExec $composer, string $targetRecipeCore): bool
    {
        $packages = $composer->show();

        // Loop through all the installed packages and find recipe core
        foreach ($packages as $package) {
            if ($package['name'] == 'silverstripe/recipe-core') {
                // We found recipe core but it's not our targeted version.
                if ($package['version'] != $targetRecipeCore) {
                    return false;
                } else {
                    // Let's make sure composer.lock is synced with composer.json
                    return $composer->validate();
                }
            }
        }

        return false;
    }
}
