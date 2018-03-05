<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\MutableSource;

class MutableSourceTest extends TestCase
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

        // Replace with node
        $replacement = new New_(new Name('ReplacedClass'));

        // replaceNode only looks at the attributes of the source node, so this syntax will work
        $ms->replaceNode($ast[6], $replacement);

        $ms->replaceNode($ast[4], "// replaced with a comment - node that the ; is still preseved: ");

        $ms->insertBefore($ast[6], "// inserted before\n");

        $this->assertEquals($output, $ms->getModifiedString());
    }
}
