<?php


namespace SilverStripe\Upgrader\Tests;

use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Records all nodes visited
 */
class RecordingVisitor implements NodeVisitor
{
    /**
     * @var Node[]
     */
    protected $visited = [];

    /**
     * @return Node[]
     */
    public function getVisitedNodes()
    {
        return $this->visited;
    }

    /**
     * Get filtered nodes
     *
     * @param string $type class of node to filter
     * @return Node[]
     */
    public function getVisitedNodesOfType($type)
    {
        return array_values(array_filter(
            $this->getVisitedNodes(),
            function (Node $node) use ($type) {
                return is_a($node, $type, true);
            })
        );
    }

    public function beforeTraverse(array $nodes)
    {
    }

    public function enterNode(Node $node)
    {
        $this->visited[] = $node;
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }
}
