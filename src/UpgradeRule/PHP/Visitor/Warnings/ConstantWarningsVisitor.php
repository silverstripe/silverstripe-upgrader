<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
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

    /**
     * Implement any upgrade rule provided by this spec
     *
     * @param Node|ConstFetch|ClassConstFetch $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // Skip if there is no replacement
        $replacement = $spec->getReplacement();
        if (!$replacement) {
            return;
        }

        // If replacement includes class, it's a complete substitution
        if (strstr($replacement, '::')) {
            $this->source->replaceNode($node, $replacement);
            return;
        }

        // Replace node name only
        $this->replaceNodePart($node, $node->name, $replacement);
    }
}
