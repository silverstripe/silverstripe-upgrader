<?php

namespace SilverStripe\Upgrader\CodeCollection;

use SilverStripe\Upgrader\CodeChangeSet;

/**
 * Generic implementation of CollectionInterface::applyChanges
 */
trait ChangeApplier
{
    /**
     * Returns a specific item by its relative path
     *
     * @param string $path
     * @return ItemInterface
     */
    abstract public function itemByPath($path);

    public function applyChanges(CodeChangeSet $changes)
    {
        foreach ($changes->allChanges() as $path => $change) {
            $item = $this->itemByPath($path);
            $item->setContents($change['new']);
        }
    }
}
