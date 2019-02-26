<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\DependencyUpgradeRule;
use SilverStripe\Upgrader\Composer\Rules\Rebuild;
use SilverStripe\Upgrader\Composer\Rules\RebuildDev;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\InputStream;

class RebuildDevTest extends TestCase
{

    use InitPackageCacheTrait;

    public function testRebuild()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new RebuildDev(['phpunit/phpunit' => '^5.7']);

        $reg = [
            'silverstripe/framework' => '4.2.0'
        ];
        $dev = [
            'phpunit/phpunit' => '2.0.0',
            'silverstripe/recipe-core' => '^1.0.0',
            'silverstripe/frameworktest' => 'dev-master',
            'acme/non-sensical' => '^1.2.3'
        ];

        $updatedDev = $rule->upgrade(
            $reg,
            $dev,
            $composer
        );

        // Recipe core is fixed, so we'll always get an exact version
        $this->assertEquals('^4.2.0', $updatedDev['silverstripe/recipe-core']);
        $this->assertEquals('^5.7', $updatedDev['phpunit/phpunit']);

        $this->assertEquals('^1.2.3', $updatedDev['acme/non-sensical']);
        $this->assertEquals('dev-master', $updatedDev['silverstripe/frameworktest']);
    }


    public function testApplicability()
    {
        $rule = new RebuildDev();
        $this->assertEquals(DependencyUpgradeRule::DEV_DEPENDENCY_RULE, $rule->applicability());
    }
}
