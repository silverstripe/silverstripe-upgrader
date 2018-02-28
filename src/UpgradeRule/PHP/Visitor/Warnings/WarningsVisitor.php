<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\ContainsWarnings;
use SilverStripe\Upgrader\Util\MutableSource;
use SilverStripe\Upgrader\Util\Warning;

/**
 * Triggers warnings for symbol uses that can't be upgraded automatically.
 * Does not rewrite code (returns the original).
 * Should be run *after* {@link SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses}.
 */
abstract class WarningsVisitor implements NodeVisitor, ContainsWarnings
{
    /**
     * @var ApiChangeWarningSpec[]
     */
    protected $specs = [];

    /**
     * @var ItemInterface
     */
    protected $file;

    /**
     * @var Warning[]
     */
    protected $warnings = [];

    /**
     * @var MutableSource
     */
    protected $source = null;

    /**
     * @param ApiChangeWarningSpec[] $specs
     * @param MutableSource $source
     * @param ItemInterface $file
     */
    public function __construct($specs, MutableSource $source, ItemInterface $file)
    {
        $this->specs = $specs;
        $this->file = $file;
        $this->source = $source;
    }

    public function beforeTraverse(array $nodes)
    {
    }

    public function enterNode(Node $node)
    {
        if (!$this->matchesNode($node)) {
            return;
        }

        foreach ($this->specs as $spec) {
            if ($this->matchesSpec($node, $spec)) {
                // Only match first spec that matches this node
                $this->rewriteWithSpec($node, $spec);
                $this->addWarning($node, $spec);
                return;
            }
        }
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }

    /**
     * @return Warning[]
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function addWarning(Node $node, ApiChangeWarningSpec $spec)
    {
        $this->warnings[] = new Warning(
            $this->file->getPath(),
            $node->getLine(),
            $spec->getFullMessage()
        );
    }

    /**
     * Check if a node matches the class and symbol
     *
     * @param Node $node
     * @param string $class FQCN
     * @param string $symbol
     * @return bool
     */
    protected function nodeMatchesClassSymbol(Node $node, $class, $symbol)
    {
        return $this->nodeMatchesSymbol($node, $symbol)
            && $this->nodeMatchesClass($node, $class);
    }

    /**
     * Check if a node matches a name
     *
     * @param Node $node
     * @param string $name
     * @return bool
     */
    protected function nodeMatchesSymbol(Node $node, $name)
    {
        // No symbol available
        if (!isset($node->name)) {
            return false;
        }
        return strcasecmp((string)$node->name, $name) === 0;
    }

    /**
     * Check if the type of the given node matches the given class name
     *
     * @param Node $node
     * @param string $class
     * @return bool
     */
    protected function nodeMatchesClass(Node $node, $class)
    {
        // Validate all classes
        $classCandidates = $node->getAttribute('contextTypes'); // Classes this node could be
        if (empty($classCandidates)) {
            return false;
        }

        // Check if any possible contexts are of the class type
        foreach ($classCandidates as $classCandidate) {
            if ($this->matchesClass($classCandidate, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given class matches the spec class
     *
     * @param string $candidate Class to test
     * @param string $class Class to test against
     * @return bool
     */
    protected function matchesClass($candidate, $class)
    {
        if (empty($candidate) || empty($class)) {
            false;
        }
        // equality will bypass classloading
        if (strcasecmp($class, $candidate) === 0) {
            return true;
        }
        // Check if subclass
        if (class_exists($class) && class_exists($candidate) && is_a($candidate, $class, true)) {
            return true;
        }
        return false;
    }

    /**
     * Check if this visitor matches this node
     *
     * @param Node $node
     * @return mixed
     */
    protected abstract function matchesNode(Node $node);

    /**
     * Check if this spec matches this node
     *
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected abstract function matchesSpec(Node $node, ApiChangeWarningSpec $spec);

    /**
     * Implement any upgrade rule provided by this spec
     *
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     */
    protected abstract function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec);


    /**
     * Search and replace a string within a node
     *
     * @param Node $node
     * @param Node|string $search
     * @param string $replacement
     */
    protected function replaceNodePart(Node $node, $search, $replacement)
    {
        // If search is a node, replace it directly
        if ($search instanceof Node) {
            $this->source->replaceNode($search, $replacement);
            return;
        }

        // If it's a string, hunt down the location and do positional replacement
        if (is_string($search)) {
            list($start, $length) = $this->findNameInNode($node, $search);
            if (isset($start) && isset($length)) {
                $this->source->replace($start, $length, $replacement);
            }
            return;
        }
    }

    /**
     * Given a node, return the position of $search inside it
     *
     * @param Node $node
     * @param string $search
     * @return array Array with start and length position
     */
    protected function findNameInNode(Node $node, $search)
    {
        // Limit to range of node
        list($nodeStart,) = $this->source->nodeRange($node);

        // Find the string for this method to replace
        $start = strpos($this->source->getOrigString(), $search, $nodeStart);
        if ($start === false) {
            return [null, null];
        }

        // Return position and length
        return [$start, strlen($search)];
    }
}
