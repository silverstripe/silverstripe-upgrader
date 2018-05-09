<?php

namespace SilverStripe\Upgrader\CodeCollection;

use Iterator;

/**
 * Represents a collection of code files, e.g. a module or project codebase
 */
interface CollectionInterface
{
    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     * @return Iterator
     */
    public function iterateItems(): Iterator;

    /**
     * Returns a specific item by its relative path
     *
     * @param string $path
     * @return ItemInterface
     */
    public function itemByPath(string $path): ItemInterface;

    /**
     * Apply the changes in the given changeset to this collection
     *
     * @param CodeChangeSet $changes
     * @return void
     */
    public function applyChanges(CodeChangeSet $changes): void;
}
