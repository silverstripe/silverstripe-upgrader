<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Expr\Variable;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 */
class PropertyWarningsVisitor extends WarningsVisitor
{
    public function matchesNode(Node $node)
    {
        // Must be property
        $isPropNode = (
            $node instanceof PropertyProperty ||
            $node instanceof PropertyFetch ||
            $node instanceof StaticPropertyFetch
        );
        if (!$isPropNode) {
            return false;
        }

        // Must have name
        if (!isset($node->name)) {
            return false;
        }

        // Don't process dynamic fetches ($obj->$someField)
        if ($node->name instanceof Variable) {
            return false;
        }

        return true;
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected function matchesSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        $symbol = $spec->getSymbol();

        // ::myProperty() or MyNamespace\MyClass::myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?::(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesStaticProperty($node, $matches['property']);
            }
            return $this->matchesStaticClassProperty($node, $matches['class'], $matches['property']);
        }

        // ->myProperty() or MyNamespace\MyClass->myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?->(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesInstanceProperty($node, $matches['property']);
            }
            return $this->matchesInstanceClassProperty($node, $matches['class'], $matches['property']);
        }

        // myProperty()
        if (preg_match('/^(?<property>[\w]+)$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['property']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid property spec: {$symbol}");
        return false;
    }


    /**
     * Is static, and matches class and property name
     *
     * @param Node $node
     * @param string $class FQCN
     * @param string $property
     * @return bool
     */
    protected function matchesStaticClassProperty(Node $node, $class, $property)
    {
        return ($node instanceof StaticPropertyFetch || $node instanceof PropertyProperty)
            && $this->nodeMatchesClassSymbol($node, $class, $property);
    }

    /**
     * Is instance, matches class and property name
     *
     * @param Node $node
     * @param string $class
     * @param string $property
     * @return bool
     */
    protected function matchesInstanceClassProperty(Node $node, $class, $property)
    {
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty)
            && $this->nodeMatchesClassSymbol($node, $class, $property);
    }

    /**
     * Is static, and matches property name
     *
     * @param Node $node
     * @param string $property
     * @return bool
     */
    protected function matchesStaticProperty(Node $node, $property)
    {
        return ($node instanceof StaticPropertyFetch || $node instanceof PropertyProperty)
            && $this->nodeMatchesSymbol($node, $property);
    }

    /**
     * Is instance, and matches property name
     *
     * @param Node $node
     * @param string $property
     * @return bool
     */
    protected function matchesInstanceProperty(Node $node, $property)
    {
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty)
            && $this->nodeMatchesSymbol($node, $property);
    }
}
