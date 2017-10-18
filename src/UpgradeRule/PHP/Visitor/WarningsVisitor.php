<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitor;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\ContainsWarnings;
use SilverStripe\Upgrader\Util\Warning;
use SilverStripe\Upgrader\Util\SymbolContext;

/**
 * Triggers warnings for symbol uses that can't be upgraded automatically.
 * Does not rewrite code (returns the original).
 * Should be run *after* {@link SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses}.
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class WarningsVisitor implements NodeVisitor, ContainsWarnings
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
     * @param ApiChangeWarningSpec[] $specs
     * @param ItemInterface $file
     */
    public function __construct($specs, ItemInterface $file)
    {
        $this->specs = $specs;
        $this->file = $file;
    }

    public function beforeTraverse(array $nodes)
    {
    }

    public function enterNode(Node $node)
    {
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
}
