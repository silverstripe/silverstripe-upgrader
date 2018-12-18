<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;
use SilverStripe\Upgrader\Composer\Recipe;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use Symfony\Component\Console\Style\SymfonyStyle;
use Composer\Semver\Comparator;

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
     * @var string
     */
    private $recipeCoreTarget;

    /**
     * Instantiate a new Rebuild Upgrade Rule.
     * @param string       $recipeCoreTarget
     * @param SymfonyStyle $console
     * @param string[][]      $recipeEquivalence List of packages that should be substituted with other packages
     */
    public function __construct(string $recipeCoreTarget, SymfonyStyle $console = null, array $recipeEquivalences = [])
    {
        $this->setRecipeCoreTarget($recipeCoreTarget);
        $this->setRecipeEquivalences($recipeEquivalences);
        $this->console = $console;
    }

    /**
     * Getter for the Recipe Core Targeted version.
     * @return string
     */
    public function getRecipeCoreTarget(): string
    {
        return $this->recipeCoreTarget;
    }

    /**
     * Setter for the Recipe Core Targeted version.
     * @param string $value
     * @return void
     */
    public function setRecipeCoreTarget(string $value):void
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
        $this->recipeCoreTarget = $value;
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
     */
    public function setRecipeEquivalences(array $recipeEquivalences): void
    {
        $this->recipeEquivalences = $recipeEquivalences;
    }

    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade.
     * @param  ComposerExec $composer Composer executable.
     * @return array Upgraded dependencies.
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array
    {
        $this->warnings = [];
        $original = $dependencies;

        // Update base framework version
        $dependencies = $this->switchToRecipeCore($dependencies);

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

        // Merge dependencies from our work file with the failed ones.
        $dependencies = $schemaFile->getRequire();
        foreach ($failedKeys as $failedKey) {
            $dependencies[$failedKey] = $original[$failedKey];
            $this->warnings[] = sprintf('Could not find a compatible version of `%s`', $failedKey);
        }

        // Add a new line to space out the output.
        if ($this->console) {
            $this->console->newLine();
        }

        return $dependencies;
    }

    /**
     * Replaces reference to framework or cms with recipe-core and recipe-cms.
     * @param  array $dependencies
     * @return array
     */
    public function switchToRecipeCore(array $dependencies): array
    {
        // Update base framework version
        if (isset($dependencies[SilverstripePackageInfo::FRAMEWORK])) {
            unset($dependencies[SilverstripePackageInfo::FRAMEWORK]);
        }
        $dependencies[SilverstripePackageInfo::RECIPE_CORE] = $this->getRecipeCoreTarget();
        if (isset($dependencies[SilverstripePackageInfo::CMS])) {
            unset($dependencies[SilverstripePackageInfo::CMS]);
            $dependencies[SilverstripePackageInfo::RECIPE_CMS] = $this->getRecipeCoreTarget();
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
            if ($this->isSystem($dep)) {
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
        // Add dependencies with fix versions
        foreach (['system', 'framework'] as $group) {
            foreach ($groupedDependencies[$group] as $package) {
                $composer->require($package, $dependencies[$package], $schemaFile->getBasePath(), true);
            }

            unset($groupedDependencies[$group]);
        }

        // Add other dependencies
        foreach ($groupedDependencies as $group) {
            foreach ($group as $package) {
                $composer->require($package, '', $schemaFile->getBasePath(), true);
            }
        }

        // Get new dependency versions from the temp file.
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
        $installedDependencies = [];

        // Get a list of what was installed from composer show
        $showedDependencies = $composer->show($schemaFile->getBasePath());
        foreach ($showedDependencies as $dep) {
            $installedDependencies[] = $dep['name'];
        }

        // Some dependencies might have failed to install properly. Let's make sure everything that was in the
        // original dependencies is in our list of installed even if it failed.
        $explicitDependencies = array_keys($originalDependencyConstraints);
        $installedDependencies = array_merge($installedDependencies, $explicitDependencies);
        $installedDependencies = array_unique($installedDependencies);

        // Loop through all know recipes and try to find recipes that can replace some of our dependencies.
        $toInstall = [];
        $toRemove = [];

        foreach (Recipe::getKnownRecipes() as $recipe) {
            $recipeName = $recipe->getName();
            
            $subset = $recipe->subsetOf($installedDependencies);
            if ($subset) {
                $toInstall[] = $recipeName;
                $toRemove = array_merge($toRemove, $subset);

                // Show a message to say what recipe is going to be installed and what it will replace.
                if ($this->console) {
                    $this->console->text(sprintf('Adding `%s` to substitute:', $recipeName));
                    $this->console->listing(array_intersect($subset, $explicitDependencies));
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
            // We keep recipe-core in for now to make sure whatever we install follows our framework constraint.
            if ($packageName != SilverstripePackageInfo::RECIPE_CORE) {
                $composer->remove($packageName, $schemaFile->getBasePath());
            }
        }

        foreach ($toInstall as $packageName) {
            $composer->require($packageName, '*', $schemaFile->getBasePath(), true);
        }

        if (in_array(SilverstripePackageInfo::RECIPE_CORE, $toRemove)) {
            // We ditch recipe core if need be.
            $composer->remove(SilverstripePackageInfo::RECIPE_CORE, $schemaFile->getBasePath());
        }

        // Get new dependency versions from the temp file.
        $schemaFile->parse();
    }

    /**
     * Determine if this dependency is for a PHP version or a PHP extension
     * @param  string $packageName
     * @return boolean
     */
    protected function isSystem(string $packageName): bool
    {
        return
            preg_match('/^php$/', $packageName) ||
            preg_match('/^ext-[a-z0-9]+$/', $packageName);
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
            SilverstripePackageInfo::CMS
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
}
