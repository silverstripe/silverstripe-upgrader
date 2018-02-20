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

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $isFunctionNode = (
            $node instanceof FuncCall
        );

        // Don't process dynamic fetches ($obj->$someVarAsMethod())
        $isNamedVarNode = (
            isset($node->name) &&
            $node->name instanceof Variable
        );

        if ($isFunctionNode && !$isNamedVarNode) {
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

        // myFunction()
        if (preg_match('/^(?<function>[\w]+)\(\)$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['function']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid function rule: {$symbol}");
        return false;
    }
}
