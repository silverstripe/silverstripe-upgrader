<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Class ClassToTraitVisitor
 * @package SilverStripe\Upgrader\UpgradeRule\PHP\Visitor
 */
class ClassToTraitVisitor implements NodeVisitor
{
    use NodeMatchable;

    /**
     * @var MutableSource
     */
    protected $source = null;

    /**
     * @var array
     */
    protected $classToTraits;

    public function __construct(MutableSource $source, array $classToTraits)
    {
        $this->source = $source;
        $this->classToTraits = $classToTraits;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Class_) {
            $extends = $node->extends;
            $extendsName = $this->getNodeName($extends);

            foreach($this->classToTraits as $class => $traits) {
                if ($class == $extendsName) {
                    // remove 'extends' from the class
                    $newClassNode = clone $node;
                    $newClassNode->extends = null;

                    // add the new traits to the class
                    foreach($traits as $traitNamespace => $traitClass) {
                        $newClassNode->stmts []= new TraitUse([new Name($traitClass)]);

                        $parts = explode("\\", $traitNamespace);
                        $this->source->insertBefore($node, new Use_([new UseUse(new Name($parts))]));
                    }

                    $this->source->replaceNode($node, $newClassNode);
                    break;
                }
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
