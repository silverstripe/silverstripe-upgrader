<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\ClassConstFetch;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class ConstantWarningsVisitor extends WarningsVisitor
{
    public function matchesNode(Node $node)
    {
        return $node instanceof ConstFetch ||
            $node instanceof ClassConstFetch;
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected function matchesSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        $symbol = $spec->getSymbol();

        // ::MY_CONST or MyNamespace\MyClass::MY_CONST
        if (preg_match('/^((?<class>[\w\\\\]*)?::)?(?<const>[\w]+)$/', $symbol, $matches)) {
            // Rule is symbol only
            if (empty($matches['class'])) {
                return $this->nodeMatchesSymbol($node, $matches['const']);
            }
            // Rule is qualified by class
            return $this->matchesClassConstant($node, $matches['class'], $matches['const']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid constant spec: {$symbol}");
        return false;
    }

    /**
     * @param Node $node
     * @param string $class
     * @param string $const
     * @return bool
     */
    protected function matchesClassConstant(Node $node, $class, $const)
    {
        return $this->nodeMatchesSymbol($node, $const) && $this->nodeMatchesClass($node, $class);
    }
}
