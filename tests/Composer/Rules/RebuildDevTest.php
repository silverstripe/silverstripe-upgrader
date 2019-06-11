<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use Composer\Semver\Semver;
use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\DependencyUpgradeRule;
use SilverStripe\Upgrader\Composer\Rules\RebuildDev;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;
use SilverStripe\Upgrader\Composer\ComposerExec;

class RebuildDevTest extends TestCase
{

    use InitPackageCacheTrait;

    public function testUpgrade()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new RebuildDev(['phpunit/phpunit' => '^5.7']);

        $result = $rule->upgrade(
            ['silverstripe/recipe-cms' => '^4.3'],
            ['phpunit/phpunit' => '^3 || ^4 || ^5'],
            $composer
        );

        $this->assertArrayHasKey('phpunit/phpunit', $result);
        $this->assertEquals('^5.7', $result['phpunit/phpunit']);
        $this->assertEmpty($rule->getWarnings());
    }

    public function testUpgradeWithoutFixDependency()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new RebuildDev([]);

        $result = $rule->upgrade(
            ['silverstripe/recipe-cms' => '^4.3'],
            ['phpunit/phpunit' => '^3 || ^4 || ^5'],
            $composer
        );

        $this->assertArrayHasKey('phpunit/phpunit', $result);
        $this->assertNotEquals('^5.7', $result['phpunit/phpunit']);
        $this->assertEmpty($rule->getWarnings());
    }

    public function testUpgradeWithFailure()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new RebuildDev(['phpunit/phpunit' => '^5.7']);

        $result = $rule->upgrade(
            ['silverstripe/recipe-cms' => '^4.3'],
            ['phpunit/phpunit' => '^3 || ^4 || ^5', 'non-sense/foo-bar' => '^1.2.3'],
            $composer
        );

        $this->assertArrayHasKey('phpunit/phpunit', $result);
        $this->assertArrayHasKey('non-sense/foo-bar', $result);
        $this->assertEquals('^1.2.3', $result['non-sense/foo-bar']);
        $this->assertNotEmpty($rule->getWarnings());
    }

    public function testUpgradeWithOtherPackages()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new RebuildDev(['phpunit/phpunit' => '^5.7']);

        $result = $rule->upgrade(
            ['silverstripe/recipe-cms' => '^4.3'],
            [
                'phpunit/phpunit' => '^3 || ^4 || ^5',
                'silverstripe/frameworktest' => 'dev-3',
                'lekoala/silverstripe-debugbar' => '^1.2'
            ],
            $composer
        );

        $this->assertArrayHasKey('phpunit/phpunit', $result);
        $this->assertArrayHasKey('silverstripe/frameworktest', $result);
        $this->assertArrayHasKey('lekoala/silverstripe-debugbar', $result);
        $this->assertNotEquals('dev-3', $result['silverstripe/frameworktest']);
        $this->assertFalse(
            Semver::satisfies('1.2.0', $result['lekoala/silverstripe-debugbar']),
            'lekoala/silverstripe-debugbar should have been upgraded to a version greater than 2.0'
        );

        $this->assertEmpty($rule->getWarnings());
    }

    public function testApplicability()
    {
        $rule = new RebuildDev();
        $this->assertEquals(DependencyUpgradeRule::DEV_DEPENDENCY_RULE, $rule->applicability());
    }
}
