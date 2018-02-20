<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

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
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $isClassNode = (
            $node instanceof Class_ ||
            $node instanceof StaticCall ||
            $node instanceof New_
        );
        if ($isClassNode) {
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
        $class = $spec->getSymbol();

        // class MyClass
        if (isset($node->name) && $this->matchesClass((string)$node->name, $class)) {
            return true;
        }

        // MyClass::someMethod()
        if (isset($node->class) && $this->matchesClass((string)$node->class, $class)) {
            return true;
        }

        return false;
    }
}
