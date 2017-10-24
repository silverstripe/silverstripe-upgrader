<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class MethodWarningsVisitor extends WarningsVisitor
{

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $isMethodNode = (
            $node instanceof MethodCall ||
            $node instanceof StaticCall ||
            $node instanceof ClassMethod
        );

        if ($isMethodNode) {
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

        // MyClass::myMethod() or MyNamespace\MyClass::myMethod()
        if (preg_match('/(?<class>.*)::(?<method>.*)\(\)/', $symbol, $matches)) {
            return $this->matchesStaticClassAndMethod($node, $matches['class'], $matches['method']);
        }

        // MyClass->myMethod() or MyNamespace\MyClass->myMethod()
        if (preg_match('/(?<class>.*)->(?<method>.*)\(\)/', $symbol, $matches)) {
            return $this->matchesInstanceClassAndMethod($node, $matches['class'], $matches['method']);
        }

        // myMethod()
        if (preg_match('/(?<method>.*)\(\)/', $symbol, $matches)) {
            return $this->matchesMethod($node, $matches['method']);
        }

        return false;
    }

    /**
     * @param Node $node
     * @param string $class FQCN
     * @param string $method
     * @return bool
     */
    protected function matchesStaticClassAndMethod(Node $node, $class, $method)
    {
        $context = $node->getAttribute('symbolContext');

        return (
            (string)$node->name === $method &&
            $context['staticClass'] === $class
        );
    }

    /**
     * @param Node $node
     * @param string $class FQCN
     * @param string $method
     * @return bool
     */
    protected function matchesInstanceClassAndMethod(Node $node, $class, $method)
    {
        $context = $node->getAttribute('symbolContext');
        return (
            (string)$node->name === $method &&
            (
                in_array($class, $context['methodClasses']) ||
                in_array($class, $context['uses'])
            )
        );
    }

    /**
     * @param Node $node
     * @param string $method
     * @return bool
     */
    protected function matchesMethod(Node $node, $method)
    {
        return ((string)$node->name === $method);
    }
}
