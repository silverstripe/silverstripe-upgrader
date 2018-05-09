<?php

namespace SilverStripe\Upgrader\CodeCollection;

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
    abstract public function itemByPath(string $path): ItemInterface;

    /**
     * Apply the changes contained in the CodeChangeSet.
     * @param CodeChangeSet $changes
     * @return void
     */
    public function applyChanges(CodeChangeSet $changes): void
    {
        foreach ($changes->allChanges() as $path => $change) {
            $item = $this->itemByPath($path);
            $item->setContents($change['new']);
        }
    }
}
