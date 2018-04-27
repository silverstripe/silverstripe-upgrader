<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\Rules\MatchFrameworkVersion;
use SilverStripe\Upgrader\Tests\Composer\InitPackageCacheTrait;

class MatchFrameworkVersionTest extends TestCase
{

    use InitPackageCacheTrait;

    public function testUpgrade()
    {
        $rule = new MatchFrameworkVersion('3.6.0');


        $this->assertEquals(
            $rule->upgrade(['silverstripe/cms' => '^3.2']),
            ['silverstripe/cms' => '^3.6.5']
        );

    }

}
