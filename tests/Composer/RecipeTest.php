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

    public function testSubsetOfWithRecipeAlreadyInstalled()
    {
        $recipe = new Recipe(new Package(SilverstripePackageInfo::RECIPE_CMS));

        // All these packages are implied by recipe CMS
        $expected = [
            "silverstripe/admin",
            "silverstripe/asset-admin",
            "silverstripe/campaign-admin",
            "silverstripe/errorpage",
            "silverstripe/framework"
        ];

        // Let's add recipe cms to the list
        $dependencies = array_merge([SilverstripePackageInfo::RECIPE_CMS], $expected);


        $results = $recipe->subsetOf($dependencies);
        $this->assertEquals(
            sort($expected),
            sort($results),
            'When a recipe is already in the list Recipe::subset should return the list of packages that can be removed'
        );
    }

    public function testKnownRecipes()
    {
        /**
         * @var Recipe
         */
        $recipes = [];
        foreach (Recipe::getKnownRecipes() as $recipe) {
            $recipes[$recipe->getName()] = $recipe;
        }

        $this->assertTrue(isset($recipes['silverstripe/recipe-core']));
        $this->assertTrue(isset($recipes['silverstripe/recipe-cms']));
        $this->assertTrue(isset($recipes['cwp/cwp-recipe-core']));
        $this->assertTrue(isset($recipes['cwp/cwp-recipe-cms']));
        $this->assertFalse(isset($recipes['silverstripe/cms']));
        $this->assertFalse(isset($recipes['silverstripe/framework']));
    }
}
