<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\Rebuild;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;

class RebuildTest extends TestCase
{

    use InitPackageCacheTrait;

    public function testUpgrade()
    {
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
