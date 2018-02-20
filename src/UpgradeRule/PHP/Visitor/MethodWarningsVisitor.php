<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'contextTypes' attribute of nodes.
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

        // Don't process dynamic fetches ($obj->$someField)
        $isNamedVarNode = (
            isset($node->name) &&
            $node->name instanceof Variable
        );

        if ($isMethodNode && !$isNamedVarNode) {
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

        // ::myMethod() or MyNamespace\MyClass::myMethod()
        if (preg_match('/^(?<class>[\w\\\\]*)?::(?<method>[\w]+)\(\)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesStaticMethod($node, $matches['method']);
            }
            return $this->matchesStaticClassAndMethod($node, $matches['class'], $matches['method']);
        }

        // ->myMethod() or MyNamespace\MyClass->myMethod()
        if (preg_match('/^(?<class>[\w\\\\]*)?->(?<method>[\w]+)\(\)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesInstanceMethod($node, $matches['method']);
            }
            return $this->matchesInstanceClassAndMethod($node, $matches['class'], $matches['method']);
        }

        // myMethod()
        if (preg_match('/^(?<method>[\w]+)\(\)$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['method']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid method spec: {$symbol}");
        return false;
    }

    /**
     * Is static, and matches class and method name
     *
     * @param Node $node
     * @param string $class FQCN
     * @param string $method
     * @return bool
     */
    protected function matchesStaticClassAndMethod(Node $node, $class, $method)
    {
        return $node instanceof StaticCall && $this->nodeMatchesClassSymbol($node, $class, $method);
    }

    /**
     * Is instance, matches class and method name
     *
     * @param Node $node
     * @param string $class
     * @param string $method
     * @return bool
     */
    protected function matchesInstanceClassAndMethod(Node $node, $class, $method)
    {
        return $node instanceof MethodCall && $this->nodeMatchesClassSymbol($node, $class, $method);
    }

    /**
     * Is static, and matches method name
     *
     * @param Node $node
     * @param string $method
     * @return bool
     */
    protected function matchesStaticMethod(Node $node, $method)
    {
        return $node instanceof StaticCall && $this->nodeMatchesSymbol($node, $method);
    }

    /**
     * Is instance, and matches method name
     *
     * @param Node $node
     * @param string $method
     * @return bool
     */
    protected function matchesInstanceMethod(Node $node, $method)
    {
        return $node instanceof MethodCall && $this->nodeMatchesSymbol($node, $method);
    }
}
