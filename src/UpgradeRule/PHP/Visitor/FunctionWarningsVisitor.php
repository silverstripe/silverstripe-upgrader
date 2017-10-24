<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
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

        if ($isFunctionNode) {
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
        if (preg_match('/(?<function>.*)\(\)/', $symbol, $matches)) {
            return $this->matchesFunction($node, $matches['function']);
        }

        return false;
    }

    /**
     * @param Node $node
     * @param string $function
     * @return bool
     */
    protected function matchesFunction(Node $node, $function)
    {
        return ((string)$node->name === $function);
    }
}
