<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Rule to go through the require list and update the constraint to work with a specific version of Framework.
 */
class MatchFrameworkVersion implements DependencyUpgradeRule {

    protected $targetFramework;

    /**
     * Instanciate a new MatchFramworkVersion Upgrade Rule.
     * @param string $constraint
     */
    public function __construct($targetFramework)
    {
        $this->targetFramework = $targetFramework;
    }


    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade
     * @return array Upgraded dependencies
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array
    {
        foreach ($dependencies as $packageName => $constrain) {

            $package = new Package($packageName);

            switch ($package->getType()) {
                case 'silverstripe-module':
                case 'silverstripe-vendormodule':
                    $dependencies[$packageName] =
                        $this->upgradeModule($package) ?:
                        $dependencies[$packageName];
                    break;
            }
        }

        return $dependencies;
    }

    /**
     * Find the latest version that is compatible with our targeted version of Framework.
     * @param  Package $package
     * @return string
     */
    protected function upgradeModule(Package $package): string
    {
        $nextVersion = $package->getVersionBySilverStripeCompatibility($this->targetFramework);
        if ($nextVersion) {
            $versionString = $nextVersion->getId();
            if (preg_match('/^(\d+\.)*\d+$/', $versionString)) {
                $versionString = '^' . $versionString;
            }

            return $versionString;
        }

        return '';
    }

}
