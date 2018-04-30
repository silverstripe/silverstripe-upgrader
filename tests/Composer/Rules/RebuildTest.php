<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\Rebuild;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;

class RebuildTest extends TestCase
{

    use InitPackageCacheTrait;

    private $dependencies = [
        "php" => "^5.6|^7",
        "silverstripe/cms" => "^3.6",
        "silverstripe/framework" => "^3.6",
        "silverstripe/contentreview" => "~3",
        "silverstripe/sharedraftcontent" => "~1",
        "symbiote/silverstripe-advancedworkflow" => "~4",
        "ext-json" => '*',
        "cwp/cwp-core" => "~1.8.0",
        "composer/semver" => "^1.0",
    ];

    private $groupedDependencies = [
        'system' => ['php', 'ext-json'],
        'framework' => ['silverstripe/recipe-core', 'silverstripe/recipe-cms'],
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
            'silverstripe/framework' => '^3.6'
        ]);
        $this->assertEquals($result, ['silverstripe/recipe-core' => '1.1']);

        // Upgrading a 4.1 framework only project.
        $result = $rule->switchToRecipeCore([
            'silverstripe/recipe-core' => '1.0'
        ]);
        $this->assertEquals($result, ['silverstripe/recipe-core' => '1.1']);

        // Upgrading a 3.6 CMS project
        $result = $rule->switchToRecipeCore([
            'silverstripe/framework' => '^3.6',
            'silverstripe/cms' => '^3.6',
        ]);
        $this->assertEquals($result, [
            'silverstripe/recipe-core' => '1.1',
            'silverstripe/recipe-cms' => '1.1'
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
        $composer = new ComposerExec(__DIR__, '', true);
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
        $this->assertEquals($require['silverstripe/recipe-core'], '1.1.0');
        $this->assertEquals($require['silverstripe/recipe-cms'], '1.1.0');
    }



    public function testUpgrade()
    {
        return;

        $composer = new ComposerExec(__DIR__, '', true);
        $rule = new Rebuild('1.1');

        var_dump($rule->upgrade([
            "php" => ">=5.4.0",
            "silverstripe/cms" => "^3.6",
            "silverstripe/framework" => "^3.6",
            "silverstripe/contentreview" => "~3",
            "silverstripe/sharedraftcontent" => "~1",
            "symbiote/silverstripe-advancedworkflow" => "~4"
        ], $composer));
        die();

        var_dump($rule->upgrade([
            "php" => ">=5.4.0",
            "silverstripe/cms" => "^3.6",
            "silverstripe/framework" => "^3.6"
        ], $composer));
        die();


        var_dump($rule->upgrade([
            "php" => ">=5.4.0",
            "silverstripe/cms" => "^3.6",
            "silverstripe/framework" => "^3.6",
            "silverstripe/siteconfig" => "~3.6",
            "silverstripe/reports" => "~3.6",
            "silverstripe/googlesitemaps" => "^1.2.2",
            "heyday/silverstripe-hashpath" => "^2.0.1",
            "littlegiant/silverstripe-catalogmanager" => "~3.0.0",
            "littlegiant/silverstripe-singlepageadmin" => "~3.0.0",
            "kinglozzer/metatitle" => "~1.0.2",
            "camspiers/honeypot" => "~2.1.0",
            "silverstripe/akismet" => "~3.2.0",
            "jonom/silverstripe-betternavigator" => "~2.0.0",
            "stevie-mayhew/silverstripe-svg" => "~1.1.1",
            "mobiledetect/mobiledetectlib" => "~2.8.3",
            "symbiote/silverstripe-grouped-cms-menu" => "~2.4.0",
            "drewm/mailchimp-api" => "~1.0.0",
            "littlegiant/silverstripe-seo-editor" => "~1.0.1",
            "tractorcow/silverstripe-opengraph" => "~3.1.0",
            "silverstripe/blog" => "~2.3.0",
            "unclecheese/betterbuttons" => "~1.3.0",
            "ryanpotter/silverstripe-cms-theme" => "^1.0.0",
            "jonom/focuspoint" => "^1.0.5",
            "silverstripe/redirectedurls" => "^1.0",
            "heyday/silverstripe-cacheinclude" => "~4.0",
            "betterbrief/silverstripe-googlemapfield" => "~1.3",
            "UndefinedOffset/SortableGridField" => "^0.6.0",
            "axllent/silverstripe-email-obfuscator" => "^1.1",
            "monolog/monolog" => "^1.21",
            "webtorque/queued-mailer" => "^0.1.3",
            "silverstripe/dynamodb" => "^3.0.0",
            "silverstripe/crontask" => "^1.1.2",
            "league/flysystem" => "^1.0",
            "league/flysystem-sftp" => "^1.0",
            "league/flysystem-ziparchive" => "^1.0",
        ], $composer));
        die();

        $this->assertEquals(
            $rule->upgrade(['silverstripe/cms' => '^3.2']),
            ['silverstripe/cms' => '^3.6.5']
        );
    }
}
