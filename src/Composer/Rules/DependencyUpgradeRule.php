<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Represent a rule to upgrade a Composer file.
 */
interface DependencyUpgradeRule
{

    /**
     * Apply this rule to the provided dependency set.
     * @param  array $dependencies
     * @param  ComposerExec $composer
     * @return array Updated dependencies
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array;
}
