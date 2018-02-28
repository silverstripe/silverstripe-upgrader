<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Builder\Method;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'contextTypes' attribute of nodes.
 */
class MethodWarningsVisitor extends WarningsVisitor
{
    public function matchesNode(Node $node)
    {
        // Must be method
        $isMethodNode = (
            $node instanceof MethodCall ||
            $node instanceof StaticCall ||
            $node instanceof ClassMethod
        );
        if (!$isMethodNode) {
            return false;
        }

        // Must have name
        if (!isset($node->name)) {
            return false;
        }

        // Don't process dynamic fetches ($obj->$someField)
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
        // Parse spec
        $symbol = $spec->getSymbol();
        $matches = $this->parseMethodSpec($symbol);
        if (!$matches) {
            $spec->invalidRule("Invalid method spec: {$symbol}");
            return false;
        }

        // Check method name matches
        if (!$this->nodeMatchesSymbol($node, $matches['method'])) {
            return false;
        }

        // Check type
        if (!empty($matches['type']) && !$this->nodeMatchesCallType($node, $matches['type'])) {
            return false;
        }

        // Check class
        if (!empty($matches['class']) && !$this->nodeMatchesClass($node, $matches['class'])) {
            return false;
        }

        return true;
    }

    /**
     * Check the call type matches this node
     *
     * @param Node $node
     * @param string $type
     * @return bool
     */
    protected function nodeMatchesCallType(Node $node, $type): bool
    {
        if ($node instanceof ClassMethod) {
            // @todo validate method type
            return true;
        }
        if ($type === '::') {
            return $node instanceof StaticCall;
        }
        if ($type === '->') {
            return $node instanceof MethodCall;
        }
        return true;
    }

    /**
     * Parse the method specification into parts with class, type, and method keys
     *
     * @param string $symbol Spec to parse
     * @return array|null Successfully parsed spec, or null if invalid
     */
    protected function parseMethodSpec($symbol)
    {
        $pattern = <<<'PATTERN'
/^
(
    (?<class>[\w\\\\]+)?    # Optional class name qualifier (requires static specifier)
    (?<type>(::)|(->))      # Optional static specifier
)?
(?<method>[\w]+)            # Method name
(\(\))?                     # Optional parentheses
$/x
PATTERN;
        if (preg_match($pattern, $symbol, $matches)) {
            return $matches;
        }
        return null;
    }

    /**
     * @param Node|MethodCall|ClassMethod|StaticCall $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // Skip if there is no replacement
        $replacement = $spec->getReplacement();
        if (!$replacement) {
            return;
        }

        // Replace node name
        $target = $node->name;

        // If name a node, replace it directly
        if ($target instanceof Node) {
            $this->source->replaceNode($target, $replacement);
            return;
        }

        // If it's a string, hunt down the location and do positional replacement
        if (is_string($target)) {
            list($start, $length) = $this->findNameInNode($node, $target);
            if (isset($start) && isset($length)) {
                $this->source->replace($start, $length, $replacement);
            }
        }
    }
}
