<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\MutableString;

class MutableStringTest extends TestCase
{
    public function testMutableString()
    {
        $ms = new MutableString("Input string");

        $ms->remove(2, 3); // -> "In string"
        $ms->insert(6, "ADD"); // -> "In ADDstring"
        $ms->replace(9, 3, "REPLACE"); // -> "In ADDstrREPLACE"

        $this->assertEquals("In ADDstrREPLACE", $ms->getModifiedString());
    }
}
