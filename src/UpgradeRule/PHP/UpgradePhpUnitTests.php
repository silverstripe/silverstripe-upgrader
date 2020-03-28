<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PhpUnitVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

class UpgradePhpUnitTests extends PHPUpgradeRule
{
    public function appliesTo(ItemInterface $file)
    {
        // php file that extend TestCase
        return 'php' === $file->getExtension();
    }
    /**
     * @inheritDoc
     */
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }
        $source = new MutableSource($contents);

        $tree = $source->getAst();
        $this->transformWithVisitors($tree, [
            new PhpUnitVisitor($source),
        ]);

        return $source->getModifiedString();
    }
}
