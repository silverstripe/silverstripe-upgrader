<?php

namespace SilverStripe\Upgrader\Composer;

use Composer\Semver\Semver;

/**
 * Represent a packagist package.
 */
class PackageVersion
{

    protected $data;

    public function __construct(
        array $data
    ) {
        $this->id = $data;
        $this->data = $data;
    }

    public function getId()
    {
        return $this->data['version'];
    }

    /**
     * Retrieve the silverstripe framewor cosntraint for the provide package version if present. Return false otehrwise.
     * @return string|false
     */
    public function getFrameworkConstraint()
    {
        $require = $this->getRequire();

        // If the framework dependency is explicitly define.
        if (isset($require['silverstripe/framework'])) {
            return $require['silverstripe/framework'];
        }

        // Loop through a list of know recipe package and try to get their dependency.
        $knownRecipes = [
            'silverstripe/recipe-core',
            'silverstripe/recipe-cms',
            'silverstripe/cms',
        ];

        foreach ($knownRecipes as $recipe) {
            if (isset($require[$recipe])) {
                $subPackage = new Package($recipe);
                return $subPackage->getVersion($require[$recipe])->getFrameworkConstraint();
            }
        }

        // Let's bail.
        return false;
    }

    /**
     * Get the `require` entry for this package
     * @return array
     */
    public function getRequire(): array
    {
        return $this->data['require'];
    }

    /**
     * Determine if this specific package version will work with the provided package at the provided version.
     * @param  string $package
     * @param  string $version
     * @return bool
     */
    public function isCompatibleWith($package, $version): bool
    {
        $require = $this->getRequire();

        if (isset($require[$package])) {
            return Semver::satisfies($version, $require[$package]);
        }

        // This version doesn't impose any constrain on the `$package`.
        return true;
    }
}
