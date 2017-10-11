<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\Util\MutableSource;
use SilverStripe\Upgrader\Util\Warning;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\SymbolContextVisitor;

abstract class BaseVisitorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $input
     * @param Warning $warning
     * @return string
     */
    protected function getLineForWarning($input, Warning $warning)
    {
        $lines = explode("\n", $input);
        return $lines[$warning->getLine() - 1];
    }

    /**
     * @param string $input
     * @param string $name
     * @return \SilverStripe\Upgrader\CodeCollection\ItemInterface
     */
    protected function getMockFile($input, $name = 'test.php')
    {
        $code = new MockCodeCollection([
            $name => $input
        ]);
        return $code->itemByPath($name);
    }

    /**
     * @param string $input
     * @param NodeVisitor $visitor
     */
    protected function traverseWithVisitor($input, NodeVisitor $visitor)
    {
        $source = new MutableSource($input);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new SymbolContextVisitor());
        $traverser->addVisitor($visitor);
        $traverser->traverse($source->getAst());
    }
}