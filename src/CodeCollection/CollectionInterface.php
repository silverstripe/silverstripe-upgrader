<?php

namespace Sminnee\Upgrader\CodeCollection;

use Sminnee\Upgrader\CodeChangeSet;

/**
 * Represents a collection of code files, e.g. a module or project codebase
 */
interface CollectionInterface
{
    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     * @return Iterator
     */
    public function iterateItems();

    /**
     * Returns a specific item by its relative path
     * @return ItemInterface
     */
    public function itemByPath($path);

    /**
     * Apply the changes in the given changeset to this collection
     */
    public function applyChanges(CodeChangeSet $changes);
}
