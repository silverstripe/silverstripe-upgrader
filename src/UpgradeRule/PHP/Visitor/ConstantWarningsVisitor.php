<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class ConstantWarningsVisitor extends WarningsVisitor
{

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof ConstFetch) {
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
        return ($spec->getSymbol()=== $node->name->parts[0]);
    }

}
