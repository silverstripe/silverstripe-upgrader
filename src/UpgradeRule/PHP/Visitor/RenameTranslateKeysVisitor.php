<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeVisitorAbstract;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * PHP-Parser Visitor to handle class renaming upgrade handler for a renamed class
 */
class RenameTranslateKeysVisitor extends NodeVisitorAbstract
{
    use VisitorTrait;

    /**
     * @var array
     */
    protected $map;

    /**
     * @var MutableSource
     */
    protected $source;

    /**
     * Create new visitor
     *
     * @param MutableSource $source
     * @param array $map Class map
     */
    public function __construct(MutableSource $source, $map)
    {
        $this->source = $source;
        $this->map = $map;
    }

    /**
     * Check string node for replacement
     *
     * @param Scalar\String_ $stringNode
     */
    protected function handleStringUpdate(Scalar\String_ $stringNode)
    {
        // Check if class.key format
        $splitPos = strpos($stringNode->value, '.');
        if (!$splitPos) {
            return;
        }

        // Check if this inside _t() call
        if (! $this->isNodeRewritable($stringNode)) {
            return;
        }

        // Split parts
        $class = substr($stringNode->value, 0, $splitPos); // 'AssetAdmin'
        $rest = substr($stringNode->value, $splitPos); // '.NAME'
        $newClass = $this->getReplacementClass($class);
        if (!$newClass) {
            return;
        }

        // Substitute new node, keep quote type (double / single), fall back to single quoted
        $stringKind = $stringNode->getAttribute('kind', Scalar\String_::KIND_SINGLE_QUOTED);
        $replacement = $newClass . $rest;
        $replacementNode = new Scalar\String_(
            $replacement,
            [ 'kind' => $stringKind ]
        );
        $this->source->replaceNode($stringNode, $replacementNode);
    }

    /**
     * Return the fully-qualified classname to use instead of the given one
     *
     * @param string $className
     * @return string
     */
    protected function getReplacementClass($className)
    {
        // Regular remapping
        if (array_key_exists($className, $this->map)) {
            return $this->map[$className];
        }
        return null;
    }

    /**
     * Check if the given node should be re-written.
     * These sets of conditions are semi hard-coded:
     *  - Non-string class literal, or
     *  - Not a config in skipConfigs, and
     *  - Not an array key in any context, and
     *  - Not a const
     *
     * Note: This method relies on {@see ParentConnector}
     *
     * @param Scalar\String_ $node
     * @return bool
     */
    protected function isNodeRewritable(Scalar\String_ $node)
    {
        // Check context of this string by parent tree
        $parent = $node->getAttribute('parent');

        // Const strings aren't rewritten
        if ($parent instanceof Node\Const_) {
            return false;
        }

        // If this is a key in an array, then don't rewrite
        if ($parent instanceof Expr\ArrayItem && $parent->key === $node) {
            return false;
        }

        $translate = $this->detectTranslateCall($parent);

        // Only rewrite if inside _t() call
        if (!$translate) {
            return false;
        }

        // Validate that node doesn't have @skipUpgrade in a comment somewhere
        if ($this->detectSkipUpgrade($node)) {
            return false;
        }

        return true;
    }

    /**
     * Detect _t() method call
     *
     * @param Node $node
     * @return bool True if inside a _t() call
     */
    protected function detectTranslateCall(Node $node = null)
    {
        if (!$node) {
            return false;
        }

        // Test if method call to _t()
        if ($node instanceof Expr\FuncCall // _t()
            || $node instanceof Expr\MethodCall // $this->_t()
            || $node instanceof Expr\StaticCall // i18n::_t()
        ) {
            // Get full func call name
            $name = $node->name;
            if ($name instanceof Node\Name) {
                $name = $name->toString();
            }
            if ($name === '_t') {
                return true;
            }
        }

        // Recurse up the stack
        $parent = $node->getAttribute('parent');
        return $this->detectTranslateCall($parent);
    }

    public function leaveNode(Node $node)
    {
        // Strings containing only the class name
        if ($node instanceof Scalar\String_) {
            $this->handleStringUpdate($node);
        }
        return $node;
    }
}
