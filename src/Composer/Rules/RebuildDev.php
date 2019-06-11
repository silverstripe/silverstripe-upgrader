<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Rule to go through the list of dev dependencies and upgrade while respecting the regular dependency constraints.
 */
class RebuildDev implements DependencyUpgradeRule
{

    /**
     * @var string[]
     */
    private $warnings = ['`upgrade` was not called.'];

    /**
     * @var SymfonyStyle
     */
    private $console;

    /**
     * @inheritdoc
     * @return string
     */
    public function getActionTitle(): string
    {
        return 'Rebuilding dev dependencies';
    }


    /**
     * @inheritdoc
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @var string[]
     */
    private $fixDependencies;

    /**
     * Instantiate a new Rebuild Dev Upgrade Rule.
     * @param string[]     $fixDependencies Packages that should be set to a predefined constraint.
     * @param SymfonyStyle $console
     */
    public function __construct(
        array $fixDependencies = [],
        SymfonyStyle $console = null
    ) {
        $this->setFixDependencies($fixDependencies);
        $this->console = $console;
    }

    /**
     * Retrieve that list of targeted constraints
     * @return string[]
     */
    public function getFixDependencies(): array
    {
        return $this->fixDependencies;
    }

    /**
     * Set a list of specific target package constraint
     * @param string[] $fixDependencies
     * @return void
     */
    public function setFixDependencies(array $fixDependencies): void
    {
        $this->fixDependencies = $fixDependencies;
    }

    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade.
     * @param  array $devDependencies Development Dependencies to upgrade.
     * @param  ComposerExec $composer Composer executable.
     * @return array Upgraded dependencies.
     */
    public function upgrade(array $dependencies, array $devDependencies, ComposerExec $composer): array
    {
        $this->warnings = [];
        $original = $devDependencies;

        // Initialise an empty file
        $schemaFile = $composer->initTemporarySchema();

        $schemaFile->setRequire($dependencies);

        if ($this->console) {
            $this->console->note('Trying to re-require all packages');
        }
        $this->rebuild($devDependencies, $composer, $schemaFile);

        // find dependencies that could not be rebuilt into the file.
        $oldKeys = array_keys($devDependencies);
        $installedKeys = array_keys($schemaFile->getRequireDev());
        $failedKeys = array_diff($oldKeys, $installedKeys);

        // Remove wild cards constraint and target specific version
        if ($this->console) {
            $this->console->newLine();
            $this->console->note('Set dependency constraint to specific version.');
        }
        $this->fixDependencyVersions($composer, $schemaFile);

        // Merge dependencies from our work file with the failed ones.
        $devDependencies = $schemaFile->getRequireDev();
        foreach ($failedKeys as $failedKey) {
            $devDependencies[$failedKey] = $original[$failedKey];
            $this->warnings[] = sprintf(
                'Could not find a compatible version of `%s`.' . PHP_EOL
                . '   For suggestions on resolving conflicts, please see '
                . 'https://docs.silverstripe.org/en/4/upgrading/upgrading_project/#resolving-conflicts',
                $failedKey
            );
        }

        // Add a new line to space out the output.
        if ($this->console) {
            $this->console->newLine();
        }

        return $devDependencies;
    }

     /**
     * Re-require each dependency individually into the provided schema file. This will rebuild the file with updated
     * constraints. Note that if a constraint fails, the script just carries on and doesn't throw an exception.
     * @param  array        $dependencies        Flat array of dependencies with versions.
     * @param  ComposerExec $composer
     * @param  ComposerFile $schemaFile
     * @return void
     */
    public function rebuild(
        array $dependencies,
        ComposerExec $composer,
        ComposerFile $schemaFile
    ) {
        $fs = new Filesystem();

        // Add fixed dependency
        foreach ($this->fixDependencies as $package => $constraint) {
            if (isset($dependencies[$package])) {
                $composer->require($package, $constraint, $schemaFile->getBasePath(), true, true);
                unset($dependencies[$package]);
                $fs->remove($schemaFile->getBasePath() . '/composer.lock');
                $fs->remove($schemaFile->getBasePath() . '/vendor');
            }
        }

        // Add dependency without a pre-fix constraint.
        foreach ($dependencies as $package => $constraint) {
            $composer->require($package, '*', $schemaFile->getBasePath(), true, true);
            $fs->remove($schemaFile->getBasePath() . '/composer.lock');
            $fs->remove($schemaFile->getBasePath() . '/vendor');
        }

        // Get new dependency versions from the temp file.
        $composer->update($schemaFile->getBasePath());
        $schemaFile->parse();
    }

    /**
     * Remove unbound constraint and replace them with specific version constraint.
     * @param ComposerExec $composer
     * @param ComposerFile $schemaFile
     * @return void
     */
    public function fixDependencyVersions(
        ComposerExec $composer,
        ComposerFile $schemaFile
    ): void {
        $updatedDependencies = $schemaFile->getRequireDev();

        // Get the installed dependencies list from the lock file
        $installedPackages = [];
        $showOutput = $composer->show($schemaFile->getBasePath());
        foreach ($showOutput as $record) {
            $installedPackages[$record['name']] = $record['version'];
        }

        // Loop through
        foreach ($updatedDependencies as $package => $constraint) {
            // Only fix the version if it's a non-system package and we're using a wildcard constraint
            if (!Package::isSystem($package) && $constraint == "*" && isset($installedPackages[$package])) {
                // Parsed the installed version number
                $version = $installedPackages[$package];
                if (preg_match('/^([0-9]+\.[0-9]+)(\.[0-9]+)?/', $version, $matches)) {
                    $version = '^' . $matches[1] . (empty($matches[2]) ? '.0' : $matches[2]);
                }

                // Parsed branch specific constraint
                if (preg_match('/^(dev-[^ ]+)/', $version, $matches)) {
                    $version = $matches[0];
                }

                // Re require the package but with an exact version number
                $composer->require(
                    $package,
                    $version,
                    $schemaFile->getBasePath(),
                    true,
                    true
                );
            }
        }

        // Reload the composer file into our schemaFile
        $schemaFile->parse();
    }

    /**
     * @return integer
     */
    public function applicability(): int
    {
        return DependencyUpgradeRule::DEV_DEPENDENCY_RULE;
    }
}
