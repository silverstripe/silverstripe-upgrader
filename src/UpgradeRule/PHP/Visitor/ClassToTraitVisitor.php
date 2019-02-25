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
 *
 * Replaces occurrences of class extensions with traits.
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

    public function beforeTraverse(array $nodes)
    {
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }
}
