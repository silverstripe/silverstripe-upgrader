<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
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
        $nodeClass = $this->getNodeClass($node);
        return $nodeClass && $this->matchesClass($nodeClass, $class);
    }

    /**
     * Get name of class this node refers to
     *
     * @param Node|string $node
     * @return null|string
     */
    protected function getNodeClass($node) {
        // Literall name passed in
        if (is_string($node)) {
            return $node;
        }
        if ($node instanceof Name) {
            return $node->toString();
        }
        // Base supported nodes
        if ($node instanceof StaticCall || $node instanceof New_) {
            return $this->getNodeClass($node->class);
        }
        if ($node instanceof Class_) {
            return $this->getNodeClass($node->name);
        }
        return null;
    }
}
