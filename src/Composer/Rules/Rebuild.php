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
 */
class Rebuild implements DependencyUpgradeRule
{

    const CWP_RECIPE_REGEX = '/^cwp\/cwp-recipe-/';
    const CWP_MODULE_REGEX = '/^cwp\//';
    const SS_RECIPE_REGEX = '/^silverstripe\/recipe-/';

    /**
     * @var string[]
     */
    private $warnings = ['`upgrade` was not called.'];

    /**
     * @var SymfonyStyle
     */
    private $console;

    /**
     * List of packages that should be substituted with other packages
     * @var string[][]
     */
    private $recipeEquivalences = [];

    /**
     * @inheritdoc
     * @return string
     */
    public function getActionTitle(): string
    {
        return 'Rebuilding dependencies';
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
    private $targets;

    /**
     * Instantiate a new Rebuild Upgrade Rule.
     * @param string[]     $targets Package to targets and what version.
     * @param string[][]   $recipeEquivalences List of packages that should be substituted with other packages.
     * @param SymfonyStyle $console
     */
    public function __construct(
        array $targets = [],
        array $recipeEquivalences = [],
        SymfonyStyle $console = null
    ) {
        $this->setTargets($targets);
        $this->setRecipeEquivalences($recipeEquivalences);
        $this->console = $console;
    }

    /**
     * Getter for the Recipe Core Targeted version.
     * @return string
     */
    public function getRecipeCoreTarget(): string
    {
        return isset($this->targets[SilverstripePackageInfo::RECIPE_CORE])
            ? $this->targets[SilverstripePackageInfo::RECIPE_CORE]
            : '';
    }

    /**
     * Setter for the Recipe Core Targeted version.
     * @param string $value
     * @return void
     */
    public function setRecipeCoreTarget(string $value):void
    {
        $this->targets[SilverstripePackageInfo::RECIPE_CORE] = $this->normaliseRecipeVersion($value);
    }

    /**
     * recipe-core and recipe-cms switch from being 1.x based to being 4.x based. This converts 4.0 and 4.1 to the 1.x
     * version and 1.2 and above to the 4.x version.
     * @param string $value
     * @return string
     */
    private function normaliseRecipeVersion(string $value): string
    {
        if (Comparator::greaterThanOrEqualTo($value, '4.0') &&
            Comparator::lessThan($value, '4.2')) {
            // If the value is between 4.0 and 4.2, convert it to the the 1.x equivalent.
            // This is necessary because recipe-core and recipe-cms were originally release with a 1.0 version and got
            // renumbered to 4.x with 4.2
            $value = preg_replace('/^4/', '1', $value);
        } elseif (// Rewrite any version constraint above 1.2 but below 2 to 4.x
            Comparator::greaterThanOrEqualTo($value, '1.2') &&
            Comparator::lessThan($value, '2.0')) {
            $value = preg_replace('/^1/', '4', $value);
        }

        return $value;
    }

    /**
     * Retrieve that list of targeted constraints
     * @return string[]
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * Set a list of specific target package constraint
     * @param string[] $targets
     * @return void
     */
    public function setTargets(array $targets): void
    {
        $this->targets = $targets;
        if (isset($targets[SilverstripePackageInfo::RECIPE_CORE])) {
            $this->setRecipeCoreTarget($targets[SilverstripePackageInfo::RECIPE_CORE]);
        }
    }

    /**
     * Get list of packages that should be substituted  by other recipes.
     * @return string[][]
     */
    public function getRecipeEquivalences(): array
    {
        return $this->recipeEquivalences;
    }

    /**
     * Set list of packages that should be substituted  by other recipes.
     * @param string[][] $recipeEquivalences
     * @return void
     */
    public function setRecipeEquivalences(array $recipeEquivalences): void
    {
        $this->recipeEquivalences = $recipeEquivalences;
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

        // Update base framework version
        $dependencies = $this->switchToRecipes($dependencies);
        $original = $dependencies;

        // Categorise the dependencies
        $groupedDependencies = $this->groupDependenciesByType($dependencies);

        // Initialise an empty file
        $schemaFile = $composer->initTemporarySchema();

        if ($this->console) {
            $this->console->note('Trying to re-require all packages');
        }
        $this->rebuild($dependencies, $groupedDependencies, $composer, $schemaFile);

        // find dependencies that could not be rebuilt into the file.
        $oldKeys = array_keys($dependencies);
        $installedKeys = array_keys($schemaFile->getRequire());
        $failedKeys = array_diff($oldKeys, $installedKeys);

        // Try to switch to recipes where possible.
        if ($this->console) {
            $this->console->newLine();
            $this->console->note('Trying to curate dependencies by switching to recipes.');
        }

        $this->findRecipeEquivalence($dependencies, $composer, $schemaFile);

        // Remove wild cards constraint and target specific version
        if ($this->console) {
            $this->console->newLine();
            $this->console->note('Set dependency constraint to specific version.');
        }
        $this->fixDependencyVersions($composer, $schemaFile);

        // Merge dependencies from our work file with the failed ones.
        $dependencies = $schemaFile->getRequire();
        foreach ($failedKeys as $failedKey) {
            $dependencies[$failedKey] = $original[$failedKey];
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

        return $dependencies;
    }

    /**
     * Replaces reference to legacy packages with their recipe equivalents.
     * @param  array $dependencies
     * @return array
     */
    public function switchToRecipes(array $dependencies): array
    {
        $equivalences = $this->getRecipeEquivalences();

        $intersections = array_keys(array_intersect_key($dependencies, $equivalences));

        foreach ($intersections as $package) {
            unset($dependencies[$package]);
            foreach ($equivalences[$package] as $equivalentRecipe) {
                $dependencies[$equivalentRecipe] = '*';
            }
        }

        return $dependencies;
    }

    /**
     * Categorise dependencies by types.
     * @internal This allows us to sort the dependencies from the most important to the least important for our
     * constraints. e.g.: We care about our Framework constraint a lot more than we care about an unrelated 3rd party
     * package.
     * @param  array $dependencies Flat array of dependencies.
     * @return array Array of categorise dependencies.
     */
    public function groupDependenciesByType(array $dependencies)
    {
        $groups = [
            'system' => [],
            'framework' => [],
            'recipe' => [],
            'cwp' => [],
            'supported' => [],
            'other' => [],
        ];

        foreach ($dependencies as $dep => $version) {
            if (Package::isSystem($dep)) {
                $groups['system'][] = $dep;
            } elseif ($this->isFramework($dep)) {
                $groups['framework'][] = $dep;
            } elseif ($this->isRecipe($dep)) {
                $groups['recipe'][] = $dep;
            } elseif ($this->isCwp($dep)) {
                $groups['cwp'][] = $dep;
            } elseif ($this->isSupported($dep)) {
                $groups['supported'][] = $dep;
            } else {
                $groups['other'][] = $dep;
            }
        }

        return $groups;
    }

    /**
     * Re-require each dependency individually into the provided schema file. This will rebuild the file with updated
     * constraints. Note that if a constraint fails, the script just carries on and doesn't throw an exception.
     * @param  array        $dependencies        Flat array of dependencies with versions.
     * @param  array        $groupedDependencies Grouped array of dependencies without versions.
     * @param  ComposerExec $composer
     * @param  ComposerFile $schemaFile
     * @return void
     */
    public function rebuild(
        array $dependencies,
        array $groupedDependencies,
        ComposerExec $composer,
        ComposerFile $schemaFile
    ) {
        $fs = new Filesystem();

        // Add system dependencies
        foreach ($groupedDependencies['system'] as $package) {
             $composer->require($package, $dependencies[$package], $schemaFile->getBasePath(), true);
        }
        unset($groupedDependencies['system']);

        $targetedDependencies = $this->getTargets();
        foreach ($targetedDependencies as $package => $constraint) {
            $composer->require($package, $constraint, $schemaFile->getBasePath(), true);
            $fs->remove($schemaFile->getBasePath() . '/composer.lock');
            $fs->remove($schemaFile->getBasePath() . '/vendor');
        }

        // Add other dependencies
        foreach ($groupedDependencies as $group) {
            foreach ($group as $package) {
                if (!array_key_exists($package, $targetedDependencies)) {
                    $composer->require($package, '*', $schemaFile->getBasePath(), true);
                    $fs->remove($schemaFile->getBasePath() . '/composer.lock');
                    $fs->remove($schemaFile->getBasePath() . '/vendor');
                }
            }
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
        $updatedDependencies = $schemaFile->getRequire();

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

                // Re require the package but with an exact version number
                $composer->require(
                    $package,
                    $version,
                    $schemaFile->getBasePath(),
                    true
                );
            }
        }

        // Reload the composer file into our schemaFile
        $schemaFile->parse();
    }


    /**
     * Simplify a composer schema by replacing substituting dependencies with equivalent recipes.
     * @param array $originalDependencyConstraints
     * @param ComposerExec $composer
     * @param ComposerFile $schemaFile
     * @return void
     */
    public function findRecipeEquivalence(
        array $originalDependencyConstraints,
        ComposerExec $composer,
        ComposerFile $schemaFile
    ) {
        $schemaFile->parse();
        $targetedPackages = array_keys($this->getTargets());

        // Get a list of what was installed from composer show
        $installedDependencies = array_map(
            function ($dep) {
                return $dep['name'];
            },
            $composer->show($schemaFile->getBasePath())
        );

        // Some dependencies might have failed to install properly. Let's make sure everything that was in the
        // original dependencies is in our list of installed even if it failed.
        $explicitDependencies = array_keys(array_merge($originalDependencyConstraints, $schemaFile->getRequire()));
        $installedDependencies = array_merge($installedDependencies, $explicitDependencies);
        $installedDependencies = array_unique($installedDependencies);

        // Loop through all know recipes and try to find recipes that can replace some of our dependencies.
        $toInstall = [];
        $toRemove = [];

        foreach (Recipe::getKnownRecipes() as $recipe) {
            $recipeName = $recipe->getName();
            $subset = $recipe->subsetOf(array_merge($installedDependencies, $toInstall));

            $subset = array_intersect($subset, array_merge($explicitDependencies, $toInstall));
            if (!empty($subset)) {
                $toInstall[] = $recipeName;
                $toRemove = array_merge($toRemove, $subset);

                // Show a message to say what recipe is going to be installed and what it will replace.
                if ($this->console) {
                    $this->console->text(sprintf('Adding `%s` to substitute:', $recipeName));
                    $this->console->listing($subset);
                }
            }
        }

        // Clean up our arrays and make sure there's nothing in $toInstall that's also in $toRemove.
        $toRemove = array_unique($toRemove);
        $toInstall = array_diff($toInstall, $toRemove);
        // Don't try to install the dependency if it's already installed
        $toInstall = array_diff($toInstall, $explicitDependencies);

        // Start by removing packages
        foreach ($toRemove as $packageName) {
            // We keep targeted packages in for now to make sure whatever we install respect our targeted constraints
            if (!in_array($packageName, $targetedPackages) && !Package::isSystem($packageName)) {
                $composer->remove($packageName, $schemaFile->getBasePath());
            }
        }

        foreach ($toInstall as $packageName) {
            $composer->require($packageName, '*', $schemaFile->getBasePath(), true);
        }

        // Remove targeted packages that we didn't remove on the first pass
        $targetedPackagesToRemove = array_intersect($toRemove, $targetedPackages);
        foreach ($targetedPackagesToRemove as $packageName) {
            $composer->remove($packageName, $schemaFile->getBasePath());
        }

        // Get new dependency versions from the temp file.
        $schemaFile->parse();
    }

    /**
     * Determine if this dependency is for a framework level dependency (CMS or Framwork basically.)
     * @param  string $packageName
     * @return boolean
    */
    protected function isFramework(string $packageName): bool
    {
        return in_array($packageName, [
            SilverstripePackageInfo::FRAMEWORK,
            SilverstripePackageInfo::RECIPE_CORE,
            SilverstripePackageInfo::RECIPE_CMS,
            SilverstripePackageInfo::CMS,
            SilverstripePackageInfo::CWP_CORE,
            SilverstripePackageInfo::CWP_RECIPE_CORE,
            SilverstripePackageInfo::CWP_RECIPE_CMS,
        ]);
    }

    /**
     * Determine if this dependency is for a Recipe.
     * @param  string $packageName
     * @return boolean
     */
    protected function isRecipe(string $packageName): bool
    {
        return
            preg_match(self::CWP_RECIPE_REGEX, $packageName) ||
            preg_match(self::SS_RECIPE_REGEX, $packageName);
    }

    /**
     * Determine if this dependency is from CWP.
     * @param  string $packageName
     * @return boolean
     */
    protected function isCwp(string $packageName): bool
    {
        return preg_match(self::CWP_MODULE_REGEX, $packageName);
    }

    /**
     * Determine if the dependency is for an officially supported package.
     * @param  string $packageName
     * @return boolean
     */
    protected function isSupported(string $packageName): bool
    {
        return in_array($packageName, Package::SUPPORTED_MODULES);
    }

    /**
     * @return int
     */
    public function applicability(): int
    {
        return DependencyUpgradeRule::REGULAR_DEPENDENCY_RULE;
    }
}
