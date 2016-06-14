<?php

namespace SilverStripe\Upgrader\CodeCollection;

use Iterator;

class DiskCollection implements CollectionInterface
{

    use ChangeApplier;

    private $path;

    /**
     * Collection includes files recursively
     *
     * @var bool
     */
    protected $recursive = false;

    /**
     * Create a code collection for all files within the given root path
     *
     * If given a single file, then the colection will be limited to that file only.
     * If given a directory, the collection will include all files in this directory.
     *
     * @param string $path
     * @param bool $recursive
     */
    public function __construct($path, $recursive = true)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Path '$path' does not exist");
        }
        $this->path = $path;
        $this->recursive = $recursive;
    }

    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     *
     * @return Iterator
     */
    public function iterateItems()
    {
        // Iterate once over this file only
        if (is_file($this->path)) {
            yield new DiskItem(dirname($this->path), basename($this->path));
            return;
        }

        // Iterate over all files in this directory
        if ($this->recursive) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path));
        } else {
            $iterator = new \IteratorIterator(new \DirectoryIterator($this->path));
        }
        foreach ($iterator as $path) {
            // Fix iterator being passed in as path
            $path = (string)$path;
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
     *
     * @param string $path
     * @return ItemInterface
     */
    public function itemByPath($path)
    {
        $base = $this->path;

        // Handle single-file disk collection
        if (is_file($base)) {
            $base = dirname($base);
            if ($path !== basename($this->path)) {
                throw new \InvalidArgumentException("{$path} is not in this collection");
            }
        }

        return new DiskItem($base, $path);
    }
}
