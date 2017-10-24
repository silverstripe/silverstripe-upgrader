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

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $isConstNode = (
            $node instanceof ConstFetch ||
            $node instanceof ClassConstFetch
        );

        if ($isConstNode) {
            foreach ($this->specs as $spec) {
                if (!$this->matchesSpec($node, $spec)) {
                    continue;
                }

                $this->addWarning($node, $spec);
            }
        }
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected function matchesSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        $symbol = $spec->getSymbol();

        // MyClass::MY_CONST or MyNamespace\MyClass::MY_CONST
        if (preg_match('/(?<class>.*)::(?<const>.*)/', $symbol, $matches)) {
            return $this->matchesClassConstant($node, $matches['class'], $matches['const']);
        }

        return $this->matchesConstant($node, $symbol);
    }

    /**
     * @param Node $node
     * @param String $class
     * @param String $const
     * @return bool
     */
    protected function matchesClassConstant($node, $class, $const)
    {
        $context = $node->getAttribute('symbolContext');
        $name = (isset($node->name->parts)) ? $node->name->parts[0] : (string)$node->name;

        return (
            $name === $const &&
            $context['staticClass'] === $class
        );
    }

    /**
     * @param Node $node
     * @param String $const
     * @return boolean
     */
    protected function matchesConstant(Node $node, $const)
    {
        $name = (isset($node->name->parts)) ? $node->name->parts[0] : (string)$node->name;

        return ($name === $const);
    }
}
