<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;
use SilverStripe\Upgrader\Composer\Recipe;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use Symfony\Component\Console\Style\SymfonyStyle;
use Composer\Semver\Comparator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Rule to go through the require list and update the constraint to work with a specific version of Framework.
 *
 * You may pass a `$fixedDependencies` list to the constructor. This can contain a list of constraint to apply to
 * specific packages if present. This is mainly use for phpunit/phpunit because SilverStripe unit test are written for
 * PHPUnit 5.7, but there's no constraint preventing you from installing a later version.
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
     * @var string[]
     */
    private $fixedDependencies;

    /**
     * @inheritdoc
     * @return string
     */
    public function getActionTitle(): string
    {
        return 'Upgrading dev dependencies.';
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
     * Instantiate a new Rebuild Upgrade Rule.
     * @param string[] $fixedDependencies Dependencies that should be fix at pre-defined constraint if present.
     * @param SymfonyStyle $console
     */
    public function __construct(
        array $fixedDependencies = [],
        SymfonyStyle $console = null
    ) {
        $this->fixedDependencies = $fixedDependencies;
        $this->console = $console;
    }

    /**
     * @inheritDoc
     * @param  string[] $dependencies Existing dependencies to constrain.
     * @param  string[] $devDependencies Development Dependencies to upgrade.
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
            $this->console->note('Trying to re-require dev packages');
        }
        $this->rebuild($devDependencies, $composer, $schemaFile);

        // find dependencies that could not be rebuilt into the file.
        $oldKeys = array_keys($devDependencies);
        $installedKeys = array_keys($schemaFile->getRequireDev());
        $failedKeys = array_diff($oldKeys, $installedKeys);

        $this->fixDependencyVersions($composer, $schemaFile);

        // Merge dependencies from our work file with the failed ones.
        $devDependencies = $schemaFile->getRequireDev();
        foreach ($failedKeys as $failedKey) {
            $devDependencies[$failedKey] = $original[$failedKey];
            $this->warnings[] = sprintf('Could not find a compatible version of `%s`', $failedKey);
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

        // Add other dependencies
        foreach ($dependencies as $package => $constraint) {
            $constraint = isset($this->fixedDependencies[$package]) ? $this->fixedDependencies[$package] : '*';
            $composer->require($package, $constraint, $schemaFile->getBasePath(), true, true);
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
                } elseif (preg_match('/^(dev-.*) [0-9a-f]+/', $version, $matches)) {
                    $version = $matches[1];
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
