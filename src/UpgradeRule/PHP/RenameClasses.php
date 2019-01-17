<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ParentConnector;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\RenameClassesVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

class RenameClasses extends PHPUpgradeRule
{
    private $showPrompt;

    /**
     * RenameClasses constructor.
     * @param bool $showPrompt
     */
    public function __construct($showPrompt = false)
    {
        $this->showPrompt = $showPrompt;
    }

    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        $source = new MutableSource($contents);

        $mappings = isset($this->parameters['mappings']) ? $this->parameters['mappings'] : [];
        $skipConfig = isset($this->parameters['skipConfigs']) ? $this->parameters['skipConfigs'] : [];
        $renameWarnings = isset($this->parameters['renameWarnings']) ? $this->parameters['renameWarnings'] : [];
        $showPrompt = $this->showPrompt;

        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(), // Add FQN for class references
            new ParentConnector(), // Link child nodes to parents
            new RenameClassesVisitor($source, $mappings, $skipConfig, $renameWarnings, $showPrompt, $changeset, $file),
        ]);

        return $source->getModifiedString();
    }
}
