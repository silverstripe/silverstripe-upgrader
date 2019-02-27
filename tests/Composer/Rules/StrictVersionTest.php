<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use SilverStripe\Upgrader\Composer\Rules\DependencyUpgradeRule;
use SilverStripe\Upgrader\Composer\Rules\StrictVersion;
use InvalidArgumentException;

class StrictVersionTest extends TestCase
{

    public function testUpgrade()
    {
        $composer = new ComposerExec(__DIR__);
        $rule = new StrictVersion();

        $input = [
            'package/aaa' => '1.2.3',
            'package/aa' => '1.2',
            'package/a' => '1',
            'package/bbb' => '~1.2.3',
            'package/bb' => '~1.2',
            'package/b' => '~1',
            'package/ccc' => '^1.2.3',
            'package/cc' => '^1.2',
            'package/c' => '^1',
            'package/d' => 'dev-master',
            'package/e' => '*',
            'package/f' => '>=3',
            'package/ggg' => '^1.2.3@dev',
            'package/gg' => '^1.2@dev',
            'package/hhh' => '^1.2.x-dev',
        ];
        $output = [
            'package/aaa' => '1.2.3',
            'package/aa' => '1.2',
            'package/a' => '1',
            'package/bbb' => '~1.2.3',
            'package/bb' => '~1.2',
            'package/b' => '~1',
            'package/ccc' => '~1.2.3',
            'package/cc' => '~1.2.0',
            'package/c' => '^1',
            'package/d' => 'dev-master',
            'package/e' => '*',
            'package/f' => '>=3',
            'package/ggg' => '~1.2.3@dev',
            'package/gg' => '~1.2.0@dev',
            'package/hhh' => '^1.2.x-dev',
        ];

        $this->assertEquals(
            $rule->upgrade($input, [], $composer),
            $output
        );
    }

    public function testApplicability()
    {
        $rule = new StrictVersion();
        $this->assertEquals(DependencyUpgradeRule::REGULAR_DEPENDENCY_RULE, $rule->applicability());
    }
}
