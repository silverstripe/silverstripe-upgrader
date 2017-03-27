<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ParentConnector;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\RenameTranslateKeysVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Renames _t() translate calls
 */
class RenameTranslateKeys extends PHPUpgradeRule
{
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        // No class maps
        if (empty($this->parameters['mappings'])) {
            trigger_error("No class mappings found", E_USER_NOTICE);
            return $contents;
        }

        // Visit with translate visitor
        $source = new MutableSource($contents);
        $mappings = $this->parameters['mappings'];
        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(), // Add FQN for class references
            new ParentConnector(), // Link child nodes to parents
            new RenameTranslateKeysVisitor($source, $mappings),
        ]);

        return $source->getModifiedString();
    }
}
