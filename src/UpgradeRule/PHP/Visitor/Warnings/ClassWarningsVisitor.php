<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * Note: Does not support rewriting
 */
class ClassWarningsVisitor extends WarningsVisitor
{
    public function matchesNode(Node $node)
    {
        return $node instanceof Class_ ||
            $node instanceof StaticCall ||
            $node instanceof New_;
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected function matchesSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        $class = $spec->getSymbol();
        return $this->nodeMatchesClass($node, $class);
    }

    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // no-op as class rewrites must be done via `mapping` not `warnings`
    }
}
