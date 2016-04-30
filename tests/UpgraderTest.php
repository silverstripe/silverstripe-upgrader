<?php

namespace Sminnee\Upgrader\Tests;

use Sminnee\Upgrader\Upgrader;
use Sminnee\Upgrader\UpgradeSpec;

class UpgraderTest extends \PHPUnit_Framework_TestCase
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
            'test.php' => "bar\nfoo\nthis is my test\n",
            'bla\other.php' => "bar\nfoo\nthis is another test\n",
        ], $changes->allChanges());

        $this->assertEquals([
            'Line 2: test warning',
            'Line 3: other warning',
        ], $changes->warningsForPath('test.php'));
    }
}
