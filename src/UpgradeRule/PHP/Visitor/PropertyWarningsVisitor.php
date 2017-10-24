<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Stmt\PropertyProperty;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class PropertyWarningsVisitor extends WarningsVisitor
{

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $isPropNode = (
            $node instanceof PropertyProperty ||
            $node instanceof PropertyFetch ||
            $node instanceof StaticPropertyFetch
        );

        if ($isPropNode) {
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

        // MyClass::myProp or MyNamespace\MyClass::myProp
        if (preg_match('/(?<class>.*)::(?<prop>.*)/', $symbol, $matches)) {
            return $this->matchesStaticClassAndProp($node, $matches['class'], $matches['prop']);
        }

        // MyClass->myProp or MyNamespace\MyClass->myProp
        if (preg_match('/(?<class>.*)->(?<prop>.*)/', $symbol, $matches)) {
            return $this->matchesInstanceClassAndProp($node, $matches['class'], $matches['prop']);
        }

        // myProp
        if (preg_match('/(?<prop>.*)/', $symbol, $matches)) {
            return $this->matchesProp($node, $matches['prop']);
        }

        return false;
    }

    /**
     * @param Node $node
     * @param string $class FQCN
     * @param string $prop
     * @return bool
     */
    protected function matchesStaticClassAndProp(Node $node, $class, $prop)
    {
        $context = $node->getAttribute('symbolContext');

        return (
            (string)$node->name === $prop &&
            (
                $context['class'] === $class ||
                $context['staticClass'] === $class
            )

        );
    }

    /**
     * @param Node $node
     * @param string $class FQCN
     * @param string $prop
     * @return bool
     */
    protected function matchesInstanceClassAndProp(Node $node, $class, $prop)
    {
        $context = $node->getAttribute('symbolContext');

        return (
            (string)$node->name === $prop &&
            (
                $class === $context['class'] ||
                in_array($class, $context['methodClasses']) ||
                in_array($class, $context['uses'])
            )
        );
    }

    /**
     * @param Node $node
     * @param string $prop
     * @return bool
     */
    protected function matchesProp(Node $node, $prop)
    {
        return ((string)$node->name === $prop);
    }
}
