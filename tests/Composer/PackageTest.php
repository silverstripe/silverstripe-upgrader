<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\Packagist;

class PackageTest extends TestCase
{
    use InitPackageCacheTrait;

    public function testGetVersionNumbers()
    {
        $package = $this->getPackage('silverstripe/recipe-core');

        $expectedVersions = [
            "1.1.0",
            "1.0.3",
            "1.0.2",
            "1.0.1",
            "1.0.0",
            "1.1.0-rc2",
            "1.1.0-rc1",
            "1.0.1-rc1",
            "1.0.0-rc3",
            "1.0.0-rc2",
            "1.0.0-rc1",
            "1.0.0-beta4",
            "1.0.0-beta3",
            "1.0.0-beta2",
            "dev-master",
            "1.x-dev",
            "1.1.x-dev",
            "1.0.x-dev",
        ];

        $this->assertEquals(
            $package->getVersionNumbers(),
            $expectedVersions,
            'getVersionNumbers should return a list of package versions.'
        );

        $this->assertEquals(
            $package->getVersionNumbers('~1.1.0'),
            [
                "1.1.0",
                "1.1.0-rc2",
                "1.1.0-rc1",
                "1.1.x-dev",
            ],
            'getVersionNumbers should return a list of package versions.'
        );
    }

    public function testGetVersion()
    {
        $package = $this->getPackage('silverstripe/recipe-core');
        $version = $package->getVersion('~1.0.0');
        $this->assertEquals(
            $version->getId(),
            '1.0.3',
            '1.0.3 should be the latest version that meet respects ~1.0.0'
        );

        $version = $package->getVersion('~10.0');
        $this->assertNull($version, 'The package should not have a version 10');
    }

    public function testIsSilverStripeRelated()
    {
        $coreRecipe = $this->getPackage('silverstripe/recipe-core');
        $this->assertTrue(
            $coreRecipe->isSilverstripeRelated(),
            '`silverstripe/recipe-core` is related to silverstripe'
        );

        $coreRecipe = $this->getPackage('madewithlove/elasticsearcher', 'madewithlove-elasticsearcher.json');
        $this->assertFalse(
            $coreRecipe->isSilverstripeRelated(),
            '`madewithlove/elasticsearcher` is not silverstripe related.'
        );
    }

    /**
     * Instanciate a new package with the recipe-core info.
     * @return Package
     */
    private function getPackage($name, $file = 'recipe-core.json')
    {
        $data = json_decode(file_get_contents(__DIR__ . '/fixture/' . $file), true);

        return new Package(
            $name,
            $data['packages'][$name]
        );
    }
}
