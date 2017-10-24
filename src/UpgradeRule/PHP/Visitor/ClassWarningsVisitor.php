<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use SilverStripe\Upgrader\Util\Warning;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
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
        $symbol = $spec->getSymbol();
        $context = $node->getAttribute('symbolContext');

        $class = '';

        if (isset($node->name)) {
            $class = (string)$node->name;
        }

        // extends MyNamespace\MyClass or extends MyClass
        if (isset($node->extends->parts)) {
            $class = implode('\\', $node->extends->parts);
        }

        // MyClass::someMethod()
        if (isset($node->class->parts)) {
            $class = implode('\\', $node->class->parts);
        }

        foreach ($context['uses'] as $use) {
            // Prefix namespace if we have a match on trailing part of use statements
            if (preg_match('#'. preg_quote($class) . '$#', $use)) {
                $class = $use;
            }
        }

        return $class === $symbol;
    }
}
