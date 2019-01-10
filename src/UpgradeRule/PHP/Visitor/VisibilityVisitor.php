<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Updates visibilities of properties and methods
 *
 * Class VisibilityVisitor
 * @package SilverStripe\Upgrader\UpgradeRule\PHP\Visitor
 */
class VisibilityVisitor implements NodeVisitor
{
    use NodeMatchable;

    /**
     * @var MutableSource
     */
    protected $source = null;

    /**
     * @var array
     */
    protected $visibilities;

    public function __construct(MutableSource $source, array $visibilities)
    {
        $this->source = $source;
        $this->visibilities = $visibilities;
    }

    /**
     * @param Node $node
     * @param int $visibility
     * @return int returns whether the provided node has the visibility provided
     */
    protected static function hasVisibility($node, $visibility = Class_::MODIFIER_PRIVATE)
    {
        return ($node->flags & $visibility);
    }

    /**
     * @param $node
     * @param int $visibility
     * @return mixed
     */
    protected static function changeVisibility($node, $visibility = Class_::MODIFIER_PRIVATE)
    {
        // remove other flags
        if ($visibility != Class_::MODIFIER_PRIVATE) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PRIVATE);
        }
        if ($visibility != Class_::MODIFIER_PROTECTED) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PROTECTED);
        }
        if ($visibility != Class_::MODIFIER_PUBLIC) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PUBLIC);
        }

        // add our flag
        $node->flags |= $visibility;

        return $node;
    }

    /**
     * @param string $visibility
     * @return int|null
     */
    private static function visibilityToBitMask(string $visibility)
    {
        switch ($visibility) {
            case 'private':
                return Class_::MODIFIER_PRIVATE;
            case 'protected':
                return Class_::MODIFIER_PROTECTED;
            case 'public':
                return Class_::MODIFIER_PUBLIC;
            default:
                return null;
        }
    }

    /**
     * @param Node $node
     * @param string $symbol
     */
    private function matchesSymbol(Node $node, string $symbol)
    {
        // ::myProperty() or MyNamespace\MyClass::myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?::(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesStaticProperty($node, $matches['property']);
            }
            return $this->matchesStaticClassProperty($node, $matches['class'], $matches['property']);
        }

        // ->myProperty() or MyNamespace\MyClass->myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?->(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesInstanceProperty($node, $matches['property']);
            }
            return $this->matchesInstanceClassProperty($node, $matches['class'], $matches['property']);
        }

        // myProperty()
        if (preg_match('/^(?<property>[\w]+)$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['property']);
        }
    }

    public function enterNode(Node $node)
    {
        // the node must be a property or a function
        if (!($node instanceof Property || $node instanceof ClassMethod)) {
            return;
        }

        foreach ($this->visibilities as $symbol => $visibility) {
            $visibilityBitMask = static::visibilityToBitMask($visibility['visibility']);

            // if the node matches the symbol and does not have the expected visibility
            if ($this->matchesSymbol($node, $symbol)
                && !self::hasVisibility($node, $visibilityBitMask)
            ) {
                // update the visibility of the node
                $this->source->replaceNode($node, self::changeVisibility($node, $visibilityBitMask));
                return;
            }
        }
    }

    /**
     * Called once before traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param Node[] $nodes Array of nodes
     *
     * @return null|Node[] Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
    }

    /**
     * Called when leaving a node.
     *
     * Return value semantics:
     *  * null
     *        => $node stays as-is
     *  * NodeTraverser::REMOVE_NODE
     *        => $node is removed from the parent array
     *  * NodeTraverser::STOP_TRAVERSAL
     *        => Traversal is aborted. $node stays as-is
     *  * array (of Nodes)
     *        => The return value is merged into the parent array (at the position of the $node)
     *  * otherwise
     *        => $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|false|int|Node|Node[] Node
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
}
