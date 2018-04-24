<?php

namespace SilverStripe\Upgrader\Composer\Rules;

/**
 * Represent a rule to upgrade a Composer file.
 */
interface DependencyUpgradeRule {

    /**
     * Apply this rule to the provided depednecy set.
     * @param  array $dependencies
     * @return array Updated dependencies
     */
    public function upgrade(array $dependencies): array;

}
