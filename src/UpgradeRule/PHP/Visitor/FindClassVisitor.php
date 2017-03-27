<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt\Namespace_;

/**
 * Finds classes / traits / interfaces
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class FindClassVisitor implements NodeVisitor
{
    /**
     * List of class names declared (not used) in this file (non-fqn)
     *
     * @var array
     */
    protected $classes = [];

    /**
     * Namespace for this file
     *
     * @var Namespace_
     */
    protected $namespace = null;

    /**
     * Line number the `namespace` declaration appears on
     *
     * @var int
     */
    protected $namespaceLine = 0;

    public function beforeTraverse(array $nodes)
    {
    }

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * otherwise: $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|Node Node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof ClassLike) {
            $this->classes[] = (string)$node->name;
        }
        if ($node instanceof Namespace_) {
            $this->namespace = $node;
        }
    }

    /**
     * Called when leaving a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * false:     $node is removed from the parent array
     *  * array:     The return value is merged into the parent array (at the position of the $node)
     *  * otherwise: $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|Node|false|Node[] Node
     */
    public function leaveNode(Node $node)
    {
    }

    /**
     * Called once after traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param Node[] $nodes Array of nodes
     *
     * @return null|Node[] Array of nodes
     */
    public function afterTraverse(array $nodes)
    {
    }

    /**
     * Gets all found classes
     *
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Get namespace node in this file
     *
     * @return Namespace_
     */
    public function getNamespace()
    {
        return $this->namespace;
    }
}
