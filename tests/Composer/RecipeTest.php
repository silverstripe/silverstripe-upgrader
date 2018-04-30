<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\Recipe;

class RecipeTest extends TestCase
{
    use InitPackageCacheTrait;

    public function testBuildDependencyTree()
    {
        $recipe = new Recipe(new Package('silverstripe/recipe-cms'));

        $expected = [
            "silverstripe/admin" => [],
            "silverstripe/asset-admin" => [],
            "silverstripe/campaign-admin" => [],
            "silverstripe/errorpage" => [],
            "silverstripe/cms" => [],
            "silverstripe/graphql" => [],
            "silverstripe/recipe-core" => [
                "silverstripe/assets" => [],
                "silverstripe/config" => [],
                "silverstripe/framework" => []
            ],
            "silverstripe/reports" => [],
            "silverstripe/siteconfig" => [],
            "silverstripe/versioned" => []
        ];

        $this->assertEquals($recipe->buildDependencyTree(), $expected);
    }

    public function testSubsetOf()
    {
        $recipe = new Recipe(new Package('silverstripe/recipe-cms'));

        $this->assertEmpty(
            $recipe->subsetOf([]),
            'Calling subset an an empty list of dependencies should return nothing.'
        );

        $this->assertEmpty(
            $recipe->subsetOf(['silverstripe/admin', 'silverstripe/cms']),
            'Calling subset an an partial list of dependencies should return nothing.'
        );

        $dependencies = [
            "silverstripe/admin",
            "silverstripe/asset-admin",
            "silverstripe/campaign-admin",
            "silverstripe/errorpage",
            "silverstripe/cms",
            "silverstripe/graphql",
            "silverstripe/reports",
            "silverstripe/siteconfig",
            "silverstripe/versioned",
        ];

        $coreDependencies = [
            "silverstripe/assets",
            "silverstripe/config",
            "silverstripe/framework",
        ];


        $expected = array_merge($dependencies, $coreDependencies, ["silverstripe/recipe-core"]);


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
            ["silverstripe/recipe-core", "silverstripe/monkey"]
        ));
        $this->assertEquals(
            sort($results),
            sort($expected),
            'Recipe/core is in the list, we should get all entries from the tree'
        );
    }
}
