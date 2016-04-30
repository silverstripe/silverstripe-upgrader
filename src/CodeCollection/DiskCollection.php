<?php

namespace Sminnee\Upgrader\CodeCollection;

use Sminnee\Upgrader\CodeChangeSet;

class DiskCollection implements CollectionInterface
{

    private $path;

    /**
     * Create a code collection for all files within the given root path
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     * @return Iterator
     */
    public function iterateItems()
    {
        foreach (glob($this->path . '/*') as $path) {
            if (substr($path, 0, strlen($this->path)) == $this->path) {
                $path = substr($path, strlen($this->path));
            }
            yield new DiskItem($this->path, $path);
        }
    }

    /**
     * Returns a specific item by its relative path
     * @return ItemInterface
     */
    public function itemByPath($path)
    {
        return new DiskItem($this->path, $path);
    }

    public function applyChanges(CodeChangeSet $changes)
    {
        foreach ($changes as $path => $contents) {
            $this->itemByPath($path)->setContents($contents);
        }
    }
}
