<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\Rebuild;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;

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
        "cwp/cwp-core" => "~1.8.0",
        "composer/semver" => "^1.0",
    ];

    private $groupedDependencies = [
        'system' => ['php', 'ext-json'],
        'framework' => [SilverstripePackageInfo::RECIPE_CORE, SilverstripePackageInfo::RECIPE_CMS],
        'recipe' => [],
        'cwp' => ['cwp/cwp-core'],
        'supported' => [
            'silverstripe/contentreview',
            'silverstripe/sharedraftcontent',
            'symbiote/silverstripe-advancedworkflow'
        ],
        'other' => ['composer/semver'],
    ];

    public function testSwitchToRecipeCore()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild('1.1');

        // Upgrading a 3.6 framwork only project
        $result = $rule->switchToRecipeCore([
            SilverstripePackageInfo::FRAMEWORK => '^3.6'
        ]);
        $this->assertEquals($result, [SilverstripePackageInfo::RECIPE_CORE => '1.1']);

        // Upgrading a 4.1 framework only project.
        $result = $rule->switchToRecipeCore([
            SilverstripePackageInfo::RECIPE_CORE => '1.0'
        ]);
        $this->assertEquals($result, [SilverstripePackageInfo::RECIPE_CORE => '1.1']);

        // Upgrading a 3.6 CMS project
        $result = $rule->switchToRecipeCore([
            SilverstripePackageInfo::FRAMEWORK => '^3.6',
            SilverstripePackageInfo::CMS => '^3.6',
        ]);
        $this->assertEquals($result, [
            SilverstripePackageInfo::RECIPE_CORE => '1.1',
            SilverstripePackageInfo::RECIPE_CMS => '1.1'
        ]);
    }

    public function testGroupDependenciesByType()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild('1.1.0');

        // In practice groupDependenciesByType will only be called after switchToRecipeCore
        $dependencies = $rule->switchToRecipeCore($this->dependencies);

        $result = $rule->groupDependenciesByType($dependencies);

        $this->assertEquals($result, $this->groupedDependencies);
    }

    public function testRebuild()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild('1.1.0');
        $schema = $composer->initTemporarySchema();

        $rule->rebuild(
            $rule->switchToRecipeCore($this->dependencies),
            $this->groupedDependencies,
            $composer,
            $schema
        );

        $require = $schema->getRequire();

        // Unfortunately, our ability to unit test here is limited because the exact dependencies we'll
        // get back will vary base on what the latest version on packagist is.
        $this->assertEquals($require[SilverstripePackageInfo::RECIPE_CORE], '1.1.0');
        $this->assertEquals($require[SilverstripePackageInfo::RECIPE_CMS], '1.1.0');
    }

    public function testFindRecipeEquivalence()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new Rebuild('1.1.0');
        $schema = $composer->initTemporarySchema();
        $dependencies = $rule->switchToRecipeCore($this->dependencies);

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
        $rule = new Rebuild('4.1.1');
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
}
