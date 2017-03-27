<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Comment;
use PhpParser\Node;

/**
 * PHP upgrade config trait
 */
trait VisitorTrait
{
    /**
     * Check if this node (or any parents) has @skipUpgrade PHPDoc
     *
     * @param Node $node
     * @return bool
     */
    protected function detectSkipUpgrade(Node $node = null)
    {
        if (!$node) {
            return false;
        }

        $comments = $node->getAttribute('comments');
        if ($comments) {
            /** @var Comment $comment */
            foreach ($comments as $comment) {
                if (stripos($comment->getText(), '@skipUpgrade') !== false) {
                    return true;
                }
            }
        }

        // Recurse up the stack
        $parent = $node->getAttribute('parent');
        return $this->detectSkipUpgrade($parent);
    }
}
