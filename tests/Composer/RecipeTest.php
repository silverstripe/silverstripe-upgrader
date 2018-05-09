<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\Recipe;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;

class RecipeTest extends TestCase
{
    use InitPackageCacheTrait;

    public function testBuildDependencyTree()
    {
        $recipe = new Recipe(new Package(SilverstripePackageInfo::RECIPE_CMS));

        $expected = [
            "silverstripe/admin" => [],
            "silverstripe/asset-admin" => [],
            "silverstripe/campaign-admin" => [],
            "silverstripe/errorpage" => [],
            SilverstripePackageInfo::CMS => [],
            "silverstripe/graphql" => [],
            SilverstripePackageInfo::RECIPE_CORE => [
                "silverstripe/assets" => [],
                "silverstripe/config" => [],
                SilverstripePackageInfo::FRAMEWORK => []
            ],
            "silverstripe/reports" => [],
            "silverstripe/siteconfig" => [],
            "silverstripe/versioned" => []
        ];

        $this->assertEquals($recipe->buildDependencyTree(), $expected);
    }

    public function testSubsetOf()
    {
        $recipe = new Recipe(new Package(SilverstripePackageInfo::RECIPE_CMS));

        $this->assertEmpty(
            $recipe->subsetOf([]),
            'Calling subset an an empty list of dependencies should return nothing.'
        );

        $this->assertEmpty(
            $recipe->subsetOf(['silverstripe/admin', SilverstripePackageInfo::CMS]),
            'Calling subset an an partial list of dependencies should return nothing.'
        );

        $dependencies = [
            "silverstripe/admin",
            "silverstripe/asset-admin",
            "silverstripe/campaign-admin",
            "silverstripe/errorpage",
            SilverstripePackageInfo::CMS,
            "silverstripe/graphql",
            "silverstripe/reports",
            "silverstripe/siteconfig",
            "silverstripe/versioned",
        ];

        $coreDependencies = [
            "silverstripe/assets",
            "silverstripe/config",
            SilverstripePackageInfo::FRAMEWORK,
        ];


        $expected = array_merge($dependencies, $coreDependencies, [SilverstripePackageInfo::RECIPE_CORE]);


        $results = $recipe->subsetOf(array_merge(
            $dependencies,
            $coreDependencies,
            ["silverstripe/monkey"]
        ));
        $this->assertEquals(
            sort($results),
            sort($expected),
            'Recipe/core is not in the list, but all its depencies are'
        );

        $results = $recipe->subsetOf(array_merge(
            $dependencies,
            [SilverstripePackageInfo::RECIPE_CORE, "silverstripe/monkey"]
        ));
        $this->assertEquals(
            sort($results),
            sort($expected),
            'Recipe/core is in the list, we should get all entries from the tree'
        );
    }
}
