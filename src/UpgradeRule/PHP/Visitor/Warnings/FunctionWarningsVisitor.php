<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 */
class FunctionWarningsVisitor extends WarningsVisitor
{

    public function matchesNode(Node $node)
    {
        // Only functions
        if (!$node instanceof FuncCall) {
            return false;
        }

        // Must have name
        if (!isset($node->name)) {
            return false;
        }

        // Don't process dynamic fetches ($obj->$someVarAsMethod())
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

        // myFunction() / myFunction
        if (preg_match('/^(?<function>[\w]+)(\(\))?$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['function']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid function rule: {$symbol}");
        return false;
    }

    /**
     * @param Node|FuncCall $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // Skip if there is no replacement
        $replacement = $spec->getReplacement();
        if ($replacement) {
            // Replace only name node, not entire fuction call
            $this->replaceNodePart($node, $node->name, $replacement);
        }
    }
}
