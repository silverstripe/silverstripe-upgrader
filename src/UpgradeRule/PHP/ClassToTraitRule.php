<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ClassToTraitVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ParentConnector;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Replaces occurrences of class extensions with traits.
 *
 * Class ClassToTraitRule
 * @package SilverStripe\Upgrader\UpgradeRule\PHP
 */
class ClassToTraitRule extends PHPUpgradeRule
{

    /**
     * @var array The list of classes that should be replaced with traits.
     * The traits is an array of namespace => class names.
     */
    private $classToTraits;

    /**
     * ClassToTraitRule constructor.
     * @param array $classToTraits
     */
    public function __construct(array $classToTraits)
    {
        $this->classToTraits = $classToTraits;
    }

    public function appliesTo(ItemInterface $file)
    {
        return 'php' === $file->getExtension();
    }

    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }
        $source = new MutableSource($contents);

        $tree = $source->getAst();
        $this->transformWithVisitors($tree, [new ParentConnector()]);
        $this->transformWithVisitors($tree, [new ClassToTraitVisitor($source, $this->classToTraits)]);

        return $source->getModifiedString();
    }
}
