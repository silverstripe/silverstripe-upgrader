<?php

namespace SilverStripe\Upgrader\Composer;

/**
 * Represent a packagist package.
 */
class PackageVersion {

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

    public function getRequire()
    {
        return $this->data['require'];
    }

}
