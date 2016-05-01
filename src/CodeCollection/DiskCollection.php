<?php

namespace Sminnee\Upgrader\CodeCollection;

use Sminnee\Upgrader\CodeChangeSet;

class DiskCollection implements CollectionInterface
{

    use ChangeApplier;

    private $path;

    /**
     * Create a code collection for all files within the given root path
     * @param string $path
     */
    public function __construct($path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Path '$path' does not exist");
        }
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Path '$path' is not a directory");
        }
        $this->path = $path;
    }

    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     * @return Iterator
     */
    public function iterateItems()
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path));
        foreach ($iterator as $path) {
            if (is_dir($path) || preg_match('#/.git/#', $path)) {
                continue;
            }

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
}
