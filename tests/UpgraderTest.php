<?php

namespace SilverStripe\Upgrader\Tests;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeSpec;

class UpgraderTest extends TestCase
{
    public function testUpgrader()
    {
        $spec = new UpgradeSpec([
            (new MockUpgradeRule())->withParameters([
                'prefix' => "foo\n"
            ]),
            (new MockUpgradeRule())->withParameters([
                'prefix' => "bar\n",
                'warning' => [ 2, 'test warning']
            ]),
            (new MockUpgradeRule())->withParameters([
                'warning' => [ 3, 'other warning']
            ]),
        ]);

        $u = new Upgrader($spec);

        $codeCollection = new MockCodeCollection([
            'test.php' => "this is my test\n",
            'bla\other.php' => "this is another test\n",
        ]);

        $changes = $u->upgrade($codeCollection);

        $this->assertEquals([
            'test.php' => [
                'new' => "bar\nfoo\nthis is my test\n",
                'old' => "this is my test\n",
            ],
            'bla\other.php' => [
                'new' => "bar\nfoo\nthis is another test\n",
                'old' => "this is another test\n",
            ],
        ], $changes->allChanges());

        $this->assertEquals([
            '<info>test.php:2</info> <comment>test warning</comment>',
            '<info>test.php:3</info> <comment>other warning</comment>',
        ], $changes->warningsForPath('test.php'));
    }
}
