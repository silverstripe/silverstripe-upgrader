<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
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
}
