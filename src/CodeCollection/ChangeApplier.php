<?php

namespace SilverStripe\Upgrader\CodeCollection;

use SilverStripe\Upgrader\CodeChangeSet;

/**
 * Generic implementation of CollectionInterface::applyChanges
 */
trait ChangeApplier
{

    public function applyChanges(CodeChangeSet $changes)
    {
        foreach ($changes->allChanges() as $path => $contents) {
            $this->itemByPath($path)->setContents($contents);
        }
    }
}
