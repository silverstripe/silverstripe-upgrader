<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\Recipe;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;

/**
 * Rule to go through the require list and update the constraint to work with a specific version of Framework.
 */
class Rebuild implements DependencyUpgradeRule
{

    const CWP_RECIPE_REGEX = '/^cwp\/cwp-recipe-/';
    const CWP_MODULE_REGEX = '/^cwp\//';
    const SS_RECIPE_REGEX = '/^silverstripe\/recipe-/';

    protected $recipeCoreTarget;

    /**
     * Instanciate a new MatchFramworkVersion Upgrade Rule.
     * @param string $constraint
     */
    public function __construct($recipeCoreTarget)
    {
        $this->recipeCoreTarget = $recipeCoreTarget;
    }

    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade
     * @return array Upgraded dependencies
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array
    {

        // Update base framework version
        if (isset($dependencies['silverstripe/framework'])) {
            unset($dependencies['silverstripe/framework']);
        }
        $dependencies['silverstripe/recipe-core'] = $this->recipeCoreTarget;
        if (isset($dependencies['silverstripe/cms'])) {
            unset($dependencies['silverstripe/cms']);
            $dependencies['silverstripe/recipe-cms'] = $this->recipeCoreTarget;
        }

        // Categorise the dependencies
        $groupedDependencies = $this->groupDependenciesByType($dependencies);

        // Initialise an empty file
        $schemaFile = $composer->initTemporarySchema();

        $this->rebuild($dependencies, $groupedDependencies, $composer, $schemaFile);
        $this->convertToRecipes($dependencies, $composer, $schemaFile);

        return $schemaFile->getRequire();
    }

    /**
     * Categorise dependencies by types
     * @param  array  $dependencies Flat array of dependencies
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
            if ($this->isRecipe($dep)) {
                $groups['system'][] = $dep;
            } elseif (in_array($dep, ['silverstripe/framework', 'silverstripe/recipe-core'])) {
                $groups['core'][] = $dep;
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
     * constraints. Note that if a constraint fail, the script just carries on and doesn't throw an execption.
     * @param  array        $dependencies        Flat array of dependencies with versions.
     * @param  array        $groupedDependencies Grouped array of dependencies without versions.
     * @param  ComposerExec $composer
     * @param  ComposerFile $schemaFile
     */
    protected function rebuild(
        array $dependencies,
        array $groupedDependencies,
        ComposerExec $composer,
        ComposerFile $schemaFile
    ) {
        // Add dependencies with fix versions
        foreach (['system', 'framework'] as $group) {
            foreach ($groupedDependencies[$group] as $package) {
                $composer->require($package, $dependencies[$package], $schemaFile->getBasePath());
            }

            unset($groupedDependencies[$group]);
        }

        // Add other dependencies
        foreach ($groupedDependencies as $group) {
            foreach ($group as $package) {
                $composer->require($package, '', $schemaFile->getBasePath());
            }
        }

        // Get new dependency versions from the temp file.
        $schemaFile->parse();
    }


    protected function convertToRecipes(
        array $originalDependencies,
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
        foreach ($originalDependencies as $dep => $constrain) {
            $installedDependencies[] = $dep;
        }
        $installedDependencies = array_unique($installedDependencies);

        // Loop through all know recipes and try to find recipes that can replace some of our dependencies.
        $toInstall = [];
        $toRemove = [];

        foreach (Recipe::getKnownRecipes() as $recipe) {
            $recipeName = $recipe->getName();


            if ($recipeName == 'silverstripe/recipe-core') {
                continue;
            }

            $subset = $recipe->subsetOf($installedDependencies);
            if ($subset) {
                $toInstall[] = $recipeName;
                $toRemove = array_merge($toRemove, $subset);
            }
        }

        // Clean up our arrays and make sure there's nothing in toInstall that's also in to remove.
        $toRemove = array_unique($toRemove);
        $toInstall = array_diff($toInstall, $toRemove);

        // Start by remove packages
        echo 'package to remove' . "\n";
        var_dump($toRemove);
        foreach ($toRemove as $packageName) {
            if ($packageName != 'silverstripe/recipe-core') {
                $composer->remove($packageName, $schemaFile->getBasePath());
            }
        }

        echo 'package to install' . "\n";
        var_dump($toInstall);
        foreach ($toInstall as $packageName) {
            $composer->require($packageName, '', $schemaFile->getBasePath());
        }

        if (in_array('silverstripe/recipe-core', $toRemove)) {
            $composer->remove('silverstripe/recipe-core', $schemaFile->getBasePath());
        }

        // Get new dependency versions from the temp file.
        $schemaFile->parse();
    }

    /**
     * Determine if this dependency is for a PHP version or a PHP extension
     * @param  string  $packageName
     * @return bool
     */
    protected function isSystem(string $packageName): bool
    {
        return
            preg_match('/^php$/', $packageName) ||
            preg_match('/^ext-[a-z0-9]$/', $packageName);
    }

    /**
     * Determine if this dependency is for a Recipe.
     * @param  string  $packageName
     * @return bool
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
     * @return bool
     */
    protected function isCwp(string $packageName): bool
    {
        return preg_match(self::CWP_MODULE_REGEX, $packageName);
    }

    /**
     * Determine if the dependency is for an officially supported package.
     * @param  string $packageName
     * @return bool
     */
    protected function isSupported(string $packageName): bool
    {
        return in_array($packageName, Package::SUPPORTED_MODULES);
    }
}
