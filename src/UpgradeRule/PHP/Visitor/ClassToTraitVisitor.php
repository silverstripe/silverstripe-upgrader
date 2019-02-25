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

    /**
     * @var Node|null The last `use` import found in the source
     */
    protected $lastUse;

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
                    $reversed = array_values(array_reverse($traits));
                    foreach($reversed as $traitClass) {
                        // add the trait to the class
                        $traitUse = new TraitUse([new Name($traitClass)]);
                        array_unshift($newClassNode->stmts, $traitUse);
                    }

                    // and add the namespace import
                    foreach($traits as $traitNamespace => $traitClass) {
                        $parts = explode("\\", $traitNamespace);
                        $this->source->insertBefore($this->lastUse ?: $node, new Use_([new UseUse(new Name($parts))]));
                    }

                    $this->source->replaceNode($node, $newClassNode);
                    break;
                }
            }
        }
    }

    public function beforeTraverse(array $nodes)
    {
        foreach ($nodes as $node) {
            if ($node instanceof Use_) {
                $this->lastUse = $node;
            }
        }
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }
}
