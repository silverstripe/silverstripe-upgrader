<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Ensures that each node has a parent property
 *
 * Credit to @nikic {@link https://github.com/nikic/PHP-Parser/issues/238}
 */
class ParentConnector extends NodeVisitorAbstract
{
    /**
     * @var Node[] List of nodes in the current stack
     */
    protected $stack;

    /**
     * @param array $nodes
     */
    public function beginTraverse(array $nodes)
    {
        $this->stack = [];
    }

    public function enterNode(Node $node)
    {
        if (!empty($this->stack)) {
            $node->setAttribute('parent', end($this->stack));
        }

        $this->stack[] = $node;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);
    }
}
