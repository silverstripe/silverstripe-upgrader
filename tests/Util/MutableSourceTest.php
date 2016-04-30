<?php

namespace Sminnee\Upgrader\Tests\Util;

use Sminnee\Upgrader\Util\MutableSource;

class MutableSourceTest extends \PHPUnit_Framework_TestCase
{
    public function testMutableString()
    {
        $fixture = file_get_contents(__DIR__ .'/fixtures/mutable-source.testfixture');

        list($input, $output) = preg_split("/------+\n/", $fixture, 2);

        $ms = new MutableSource($input);
        $ast = $ms->getAST();

        // Validate that the fixutre hasn't been corrupted
        $this->assertInstanceOf('PhpParser\Node\Expr\New_', $ast[6]);
        $this->assertInstanceOf('PhpParser\Node\Expr\New_', $ast[4]);

        $ast[6]->class->parts[0] = 'ReplacedClass';

        // replaceNode only looks at the attributes of the source node, so this syntax will work
        $ms->replaceNode($ast[6], $ast[6]);

        $ms->replaceNode($ast[4], "// replaced with a comment - node that the ; is still preseved: ");

        $ms->insertBefore($ast[6], "// inserted before\n");

        $this->assertEquals($output, $ms->getModifiedString());


    }
}
