<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\Rebuild;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;

class RebuildTest extends TestCase
{

    use InitPackageCacheTrait;

    private $dependencies = [
        "php" => "^5.6|^7",
        SilverstripePackageInfo::CMS => "^3.6",
        SilverstripePackageInfo::FRAMEWORK => "^3.6",
        "silverstripe/contentreview" => "~3",
        "silverstripe/sharedraftcontent" => "~1",
        "symbiote/silverstripe-advancedworkflow" => "~4",
        "ext-json" => '*',
        SilverstripePackageInfo::CWP_CORE => "~1.8.0",
        "composer/semver" => "^1.0",
        "silverstripe/recipe-blog" => "^1.0",
        "cwp/agency-extensions" => "^1.0",
    ];

    private $groupedDependencies = [
        'system' => ['php', 'ext-json'],
        'framework' => [
            SilverstripePackageInfo::RECIPE_CMS,
            SilverstripePackageInfo::RECIPE_CORE,
            SilverstripePackageInfo::CWP_RECIPE_CORE,

        ],
        'recipe' => ['silverstripe/recipe-blog'],
        'cwp' => ['cwp/agency-extensions'],
        'supported' => [
            'silverstripe/contentreview',
            'silverstripe/sharedraftcontent',
            'symbiote/silverstripe-advancedworkflow'
        ],
        'other' => ['composer/semver'],
    ];

    private $recipeEquivalences = [
        "silverstripe/framework" => ["silverstripe/recipe-core"],
        "silverstripe/cms" => ["silverstripe/recipe-cms"],
        "cwp/cwp-recipe-basic" => ["cwp/cwp-recipe-cms"],
        "cwp/cwp-recipe-blog" => ["cwp/cwp-recipe-cms", "silverstripe/recipe-blog"],
        "cwp/cwp-core" => ["cwp/cwp-recipe-core"],
    ];

    public function testSwitchToRecipes()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild(["silverstripe/recipe-core" => "1.1"], $this->recipeEquivalences);

        // Upgrading a 3.6 framwork only project
        $result = $rule->switchToRecipes([
            SilverstripePackageInfo::FRAMEWORK => '^3.6',
            'thirdparty/package' => '^0.1'
        ]);
        $this->assertArrayHasKey(SilverstripePackageInfo::RECIPE_CORE, $result);
        $this->assertArrayHasKey('thirdparty/package', $result);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::FRAMEWORK, $result);

        // Upgrading a 3.6 CMS project
        $result = $rule->switchToRecipes([
            SilverstripePackageInfo::FRAMEWORK => '^3.6',
            SilverstripePackageInfo::CMS => '^3.6',
        ]);
        $this->assertArrayHasKey(SilverstripePackageInfo::RECIPE_CORE, $result);
        $this->assertArrayHasKey(SilverstripePackageInfo::RECIPE_CMS, $result);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::FRAMEWORK, $result);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::CMS, $result);

        // Upgrading a CWP project
        $result = $rule->switchToRecipes([
            'cwp/cwp-recipe-blog' => '^1',
            'cwp/cwp-core' => '^1',
        ]);
        $this->assertArrayHasKey('cwp/cwp-recipe-cms', $result);
        $this->assertArrayHasKey('silverstripe/recipe-blog', $result);
        $this->assertArrayHasKey('cwp/cwp-recipe-core', $result);
        $this->assertArrayNotHasKey('cwp/cwp-recipe-blog', $result);
        $this->assertArrayNotHasKey('cwp/cwp-core', $result);
    }

    public function testGroupDependenciesByType()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild(["silverstripe/recipe-core" => "1.1.0"], $this->recipeEquivalences);

        // In practice groupDependenciesByType will only be called after switchToRecipeCore
        $dependencies = $rule->switchToRecipes($this->dependencies);

        $result = $rule->groupDependenciesByType($dependencies);

        $this->assertEquals($this->groupedDependencies, $result);
    }

    public function testRebuild()
    {
        $composer = new ComposerExec(__DIR__, "");
        $rule = new Rebuild(["silverstripe/recipe-core" => "4.2.0"], $this->recipeEquivalences);
        $schema = $composer->initTemporarySchema();

        $rule->rebuild(
            $rule->switchToRecipes($this->dependencies),
            $this->groupedDependencies,
            $composer,
            $schema
        );

        $require = $schema->getRequire();

        // Unfortunately, our ability to unit test here is limited because the exact dependencies we'll
        // get back will vary base on what the latest version on packagist is.
        $this->assertEquals('4.2.0', $require[SilverstripePackageInfo::RECIPE_CORE]);
        $this->assertEquals('4.2.0', $require[SilverstripePackageInfo::RECIPE_CMS]);
    }

    public function testFindRecipeEquivalence()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild([], $this->recipeEquivalences);
        $schema = $composer->initTemporarySchema();
        $dependencies = $rule->switchToRecipes($this->dependencies);

        // Test a package that has recipe-core and recipe-cms explicitly define
        $composer->require(SilverstripePackageInfo::RECIPE_CORE, '^4.2', $schema->getBasePath());
        $composer->require(SilverstripePackageInfo::RECIPE_CMS, '^4.2', $schema->getBasePath());

        $rule->findRecipeEquivalence($dependencies, $composer, $schema);

        $require = $schema->getRequire();

        // recipe-cms and recipe-core and all should have been substituted with recipe-collaboration
        $this->assertArrayHasKey('silverstripe/recipe-collaboration', $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::RECIPE_CORE, $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::RECIPE_CMS, $require);

        // Test a composer file that doesn't define any recipe but has all underlying package install.
        $schema = $composer->initTemporarySchema();
        $pathToCollaboration = __DIR__ . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'fixture' .
            DIRECTORY_SEPARATOR . 'collaboration-recipe' .
            DIRECTORY_SEPARATOR ;
        $content = file_get_contents($pathToCollaboration . 'composer.json');
        $schema->setContents($content);
        copy(
            $pathToCollaboration . 'composer.lock',
            $schema->getBasePath() . DIRECTORY_SEPARATOR . 'composer.lock'
        );
        $composer->install($schema->getBasePath());

        $rule->findRecipeEquivalence($dependencies, $composer, $schema);

        $require = $schema->getRequire();

        // recipe-cms and recipe-core and all should have been substituted with recipe-collaboration
        $this->assertArrayHasKey('silverstripe/recipe-collaboration', $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::RECIPE_CORE, $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::RECIPE_CMS, $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::FRAMEWORK, $require);
        $this->assertArrayNotHasKey(SilverstripePackageInfo::CMS, $require);
        $this->assertArrayNotHasKey('silverstripe/contentreview', $require);
    }

    public function testRecipeCoreTarget()
    {
        $rule = new Rebuild(["silverstripe/recipe-core" => "4.1.1"]);
        $this->assertEquals('1.1.1', $rule->getRecipeCoreTarget());
        $rule->setRecipeCoreTarget('4.2.0');
        $this->assertEquals('4.2.0', $rule->getRecipeCoreTarget());
        $rule->setRecipeCoreTarget('4.0.0');
        $this->assertEquals('1.0.0', $rule->getRecipeCoreTarget());
        $rule->setRecipeCoreTarget('1.0.1');
        $this->assertEquals('1.0.1', $rule->getRecipeCoreTarget());
        $rule->setRecipeCoreTarget('1.2.0');
        $this->assertEquals('4.2.0', $rule->getRecipeCoreTarget());
    }

    public function testRecipeEquivalences()
    {
        $payload = [
            "cwp/cwp-recipe-basic" => ["cwp/cwp-recipe-cms"],
            "cwp/cwp-recipe-blog" => ["cwp/cwp-recipe-cms", "silverstripe/recipe-blog"],
            "cwp/cwp-core" => ["cwp/cwp-recipe-core"],
        ];
        $rule = new Rebuild();
        $rule->setRecipeEquivalences($payload);
        $this->assertEquals(
            $payload,
            $rule->getRecipeEquivalences()
        );
    }

    public function testTargets()
    {
        $rule = new Rebuild();
        $rule->setTargets([
            "silverstripe/recipe-core" => "4.0.0",
            "dnadesign/silverstripe-elemental" => "3.0.0"
        ]);

        $this->assertEquals(
            [
                "silverstripe/recipe-core" => "1.0.0",
                "dnadesign/silverstripe-elemental" => "3.0.0",
            ],
            $rule->getTargets()
        );
    }

    public function testFixDependencyVersions()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild([], $this->recipeEquivalences);
        $schema = $composer->initTemporarySchema();
        $expected = [];

        // Require specific version of packages
        $composer->require(SilverstripePackageInfo::FRAMEWORK, '*', $schema->getBasePath());
        $composer->require('dnadesign/silverstripe-elemental', '*', $schema->getBasePath());
        $show = $composer->show($schema->getBasePath());

        foreach ($show as $installedPackaged) {
            if (in_array(
                $installedPackaged['name'],
                [SilverstripePackageInfo::FRAMEWORK, 'dnadesign/silverstripe-elemental']
            )) {
                $expected[$installedPackaged['name']] = '^' . $installedPackaged['version'];
            }
        }



        // Update the composer file to use wildcards instead
        $json = json_decode($schema->getContents(), true);
        foreach ($json['require'] as $package => &$constraint) {
            $constraint = '*';
        }
        $schema->setContents(json_encode($json));

        // Fix dependency which should set them back to our explicit numbers
        $rule->fixDependencyVersions($composer, $schema);
        $dependencies = $schema->getRequire();

        $this->assertEquals(
            $expected,
            $dependencies
        );
    }
}
