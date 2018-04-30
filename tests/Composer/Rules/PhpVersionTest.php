<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\PhpVersion;
use InvalidArgumentException;

class PhpVersionTest extends TestCase
{

    public function testUpgrade()
    {
        $rule = new PhpVersion();

        $this->assertEquals(
            $rule->upgrade([]),
            ['php' => '>=5.6'],
            'A PHP constraint should be added if none is present to being with.'
        );

        $this->assertEquals(
            $rule->upgrade(['php' => '~5']),
            ['php' => '>=5.6'],
            'PHP constraint should not tolerate anything below 5.6 even if the initial project support other 5 version.'
        );

        $this->assertEquals(
            $rule->upgrade(['php' => '~7']),
            ['php' => '~7'],
            'PHP 7 constraint should not be rewritten'
        );
    }
}
