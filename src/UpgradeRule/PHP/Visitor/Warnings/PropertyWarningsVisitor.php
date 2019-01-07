<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\PropertyProperty;
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
     * @param Node|PropertyProperty|PropertyFetch|StaticPropertyFetch $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // Skip if there is no replacement
        $replacement = $spec->getReplacement();
        if ($replacement) {
            // Replace name only
            $this->replaceNodePart($node, $node->name, $replacement);
        }
    }
}
