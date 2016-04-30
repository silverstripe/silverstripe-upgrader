<?php

namespace Sminnee\Upgrader;

use Sminnee\Upgrader\CodeCollection\CollectionInterface;

/**
 * Passes code into various transformation operations
 */
class Upgrader
{
    public function __construct(UpgradeSpec $spec)
    {
        $this->spec = $spec;
    }

    public function upgrade(CollectionInterface $code)
    {
        $changeset = new CodeChangeSet();

        foreach ($code->iterateItems() as $item) {
            $path = $item->getPath();
            $contents = $updatedContents = $item->getContents();

            foreach ($this->spec->rules() as $upgradeRule) {
                list($updatedContents, $warnings) = $upgradeRule->upgradeFile($updatedContents, $path);
                if ($warnings) {
                    $changeset->addWarnings($path, $warnings);
                }
            }

            if ($contents !== $updatedContents) {
                $changeset->addFileChange($path, $updatedContents);
            }
        }

        return $changeset;
    }
}
