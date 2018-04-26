<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\PackageVersion;

class PackageVersionTest extends TestCase
{

    public function testGetId()
    {
        $version = $this->getRecipeCorePackageVersion();

        $this->assertEquals(
            $version->getId(),
            '1.1.0',
            'getId should return the `version` attribute a PackageVersion.'
        );
    }

    public function testRequire()
    {
        $version = $this->getRecipeCorePackageVersion();

        $this->assertEquals(
            $version->getRequire(),
            [
                "silverstripe/assets" => "1.1.0@stable",
                "silverstripe/config" => "1.0.4@stable",
                "silverstripe/framework" => "4.1.0@stable",
                "silverstripe/recipe-plugin" => "^1.1",
            ],
            ''
        );
    }

    public function testGetFrameworkConstraint()
    {
        // Test a package that requires framework directly
        $this->assertEquals(
            $this->getRecipeCorePackageVersion()->getFrameworkConstraint(),
            '4.1.0@stable',
            'Recipe-core 1.1.0 requires Framwork 4.1.0@stable'
        );

        // Test recipe core
        $corePackage = new Package('silverstripe/recipe-core');
        $this->assertEquals(
            $corePackage->getVersion('1.0.2')->getFrameworkConstraint(),
            '4.0.2@stable',
            'Recipe-core 1.0.2 requires Framwork 4.0.2@stable'
        );

        // Test a package that depends on recipe core
        $cmsPackage = new Package('silverstripe/recipe-cms');
        $this->assertEquals(
            $cmsPackage->getVersion('1.0.3')->getFrameworkConstraint(),
            '4.0.3@stable',
            'Recipe-cms 1.0.3 requires Framwork 4.0.3@stable'
        );

        // Test a package that depends on recipe cms
        $blogPackage = new Package('silverstripe/recipe-blog');
        $this->assertEquals(
            $blogPackage->getVersion('1.0.0-rc2')->getFrameworkConstraint(),
            '4.1.0@stable',
            'Recipe-blog 1.0.0-rc2 requires Framwork 4.1.0@stable'
        );


    }

    /**
     * Instanciate a new package with the recipe-core info.
     * @return PackageVersion
     */
    private function getRecipeCorePackageVersion()
    {
        $data = json_decode(file_get_contents(__DIR__ . '/fixture/recipe-core.json'), TRUE);

        return new PackageVersion(
            $data['package']['versions']['1.1.0']
        );
    }

}
