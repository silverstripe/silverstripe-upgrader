<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;

/**
 * Rule to go through the require list and update the constraint to work with a specific version of Framework.
 */
class Rebuild implements DependencyUpgradeRule {

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

        // Categorise the dependencies
        $groupedDependencies = $this->groupDependenciesByType($dependencies);

        // Initialise an empty file
        $schemaFile = $composer->initTemporaryFile();

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

        // Get new dependency versions for temp file.
        $schemaFile->parse();


        return $dependencies;
    }

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
            } else if (in_array($dep, ['silverstripe/framework', 'silverstripe/recipe-core'])) {
                $groups['core'][] = $dep;
            } else if ($this->isRecipe($dep)) {
                $groups['recipe'][] = $dep;
            } else if ($this->isCwp($dep)) {
                $groups['cwp'][] = $dep;
            } else if ($this->isSupported($dep)) {
                $groups['supported'][] = $dep;
            } else {
                $groups['other'][] = $dep;
            }
        }

        return $groups;

    }

    protected function isSystem($packageName) {
        return
            preg_match('/^php$/', $packageName) ||
            preg_match('/^ext-[a-z0-9]$/', $packageName);
    }

    protected function isRecipe($packageName) {
        return
            preg_match(self::CWP_RECIPE_REGEX, $packageName) ||
            preg_match(self::SS_RECIPE_REGEX, $packageName);
    }

    protected function isCwp($packageName) {
        return preg_match(self::CWP_MODULE_REGEX, $packageName);
    }

    protected function isSupported($packageName) {
        return in_array($packageName, Package::SUPPORTED_MODULES);
    }


}
