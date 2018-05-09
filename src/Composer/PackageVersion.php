<?php

namespace SilverStripe\Upgrader\Composer;

use Composer\Semver\Semver;

/**
 * Represent a packagist package.
 */
class PackageVersion
{

    protected $data;

    /**
     * PackageVersion constructor.
     * @param array $data
     */
    public function __construct(
        array $data
    ) {
        $this->data = $data;
    }

    /**
     * Get the exact version ID.
     * @return string
     */
    public function getId(): string
    {
        return $this->data['version'];
    }

    /**
     * Retrieve the silverstripe framework constraint for the provide package version if present. Return false
     * otherwise.
     * @return string|false
     */
    public function getFrameworkConstraint()
    {
        $require = $this->getRequire();

        // If the framework dependency is explicitly define.
        if (isset($require[SilverstripePackageInfo::FRAMEWORK])) {
            return $require[SilverstripePackageInfo::FRAMEWORK];
        }

        // Loop through a list of known recipe packages and try to get their dependency.
        $knownRecipes = [
            SilverstripePackageInfo::RECIPE_CORE,
            SilverstripePackageInfo::RECIPE_CMS,
            SilverstripePackageInfo::CMS,
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
     * @return boolean
     */
    public function isCompatibleWith(string $package, string$version): bool
    {
        $require = $this->getRequire();

        if (isset($require[$package])) {
            return Semver::satisfies($version, $require[$package]);
        }

        // This version doesn't impose any constrain on the `$package`.
        return true;
    }
}
