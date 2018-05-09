<?php

namespace SilverStripe\Upgrader\Console;

use Symfony\Component\Console\Exception\RuntimeException;
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


use InvalidArgumentException;

/**
 * Command to try to update a composer file to use SS4.
 */
class RecomposeCommand extends AbstractCommand
{
    use FileCommandTrait;

    protected function configure()
    {
        $this->setName('recompose')
            ->setDescription('Upgrade a composer file to use the latest version of SilverStripe.')
            ->setDefinition([
                $this->getRootInputOption(),
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
                    'Prefer ~ to ^ avoid accidental upgrades.',
                    ''
                )
            ]);
    }

    /**
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
        $coreTarget = $this->findTargetRecipeCore($recipeCoreConstraint);
        $strict = $input->getOption('strict');

        $console = new SymfonyStyle($input, $output);

        // Initialise our composer file.
        $composer = new ComposerExec($rootPath, $composerPath, $output);
        $schema = new ComposerFile($composer, $rootPath);


        // Set up some caching
        $this->initPackageCache($composer);

        // Set up our rules
        $rules = [
            new Rules\PhpVersion(),
            new Rules\Rebuild($coreTarget, $console),
        ];
        if ($strict) {
            $rules[] = new Rules\StrictVersion();
        }

        // Try to upgrade the project
        $change = $schema->upgrade($rules, $console);

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
                $composer->update('', true);
                $console->success('Dependencies installed successfully.');
            } catch (RuntimeException $ex) {
                $console->warning(
                    'Composer could not resolved your updated dependencies. '.
                    'You\'ll need to manually resolve conflicts.'
                );
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
}
