<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use Composer\Semver\Semver;
use SilverStripe\Upgrader\Composer\ComposerExec;


/**
 * Rule to update the PHP version to use in the composer file.
 */
class PhpVersion implements DependencyUpgradeRule {

    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade
     * @return array Upgraded dependencies
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array
    {
        // If we don't have a php constraint already, set it to php 5.6 and move on.
        if (!isset($dependencies['php'])) {
            $dependencies['php'] = '>=5.6';
            return $dependencies;
        }

        // Check which version of PHP could in theory meet our php constraint.
        $phpConstraint = $dependencies['php'];
        $phpFiveVers = ['5.0', '5.1', '5.2', '5.3', '5.4', '5.5', '5.6'];
        $satisfied = Semver::satisfiedBy($phpFiveVers, $phpConstraint);

        // If any version of PHP 5 is supported, let's set the constraint to 5.6
        if (!empty($satisfied)) {
            $dependencies['php'] = '>=5.6';
        }

        // Otherwise the PHP constraint is probably set to some version of PHP7 or some weird thing that defies our
        // comprehension. Either way, we don't do anything with it.

        return $dependencies;
    }



}
