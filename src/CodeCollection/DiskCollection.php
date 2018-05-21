<?php

namespace SilverStripe\Upgrader\CodeCollection;

use Iterator;
use InvalidArgumentException;

/**
 * Represent a collection of files on disk that can be iterated through to perform changes.
 */
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
     * File patterns to exclude
     *
     * @var array
     */
    protected $exclusions = [];

    /**
     * Create a code collection for all files within the given root path
     *
     * If given a single file, then the colection will be limited to that file only.
     * If given a directory, the collection will include all files in this directory.
     *
     * @param string $path
     * @param boolean $recursive
     * @param array $exclusions
     * @throws InvalidArgumentException If the path does not exists.
     */
    public function __construct(string $path, bool $recursive = true, array $exclusions = [])
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Path '$path' does not exist");
        }
        $this->path = $path;
        $this->recursive = $recursive;
        $this->exclusions = $exclusions;
    }

    /**
     * Returns an iterator, yieldig all ItemInterface items in this collectinn
     *
     * @return Iterator
     */
    public function iterateItems(): Iterator
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
            $path = (string)$path->getPathname();
            if (!$this->isDiskItem($path)) {
                continue;
            }

            if (substr($path, 0, strlen($this->path)) == $this->path) {
                $path = substr($path, strlen($this->path));
            }

            yield new DiskItem($this->path, $path);
        }
    }

    /**
     * Check if this path is a valid DiskItem
     *
     * @param string $path
     * @return boolean
     */
    protected function isDiskItem(string $path): bool
    {
        // Dir isn't a disk item
        if (is_dir($path)) {
            return false;
        }

        return $this->realPathExists($path);
    }

    /**
     * Checks if the provided path exist in the DiskCollection.
     * @param string $path
     * @return boolean
     */
    public function exists(string $path): bool
    {
        return $this->realPathExists($this->path . DIRECTORY_SEPARATOR .$path);
    }

    /**
     * Check if the provided path exists on the FileSystem.
     * @param string $path
     * @return boolean
     */
    protected function realPathExists(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        // Ignore git files always
        if (preg_match('#/.git/#', $path)) {
            return false;
        }

        // Check exclusions
        foreach ($this->exclusions as $exclusion) {
            if ($this->pathMatches($path, $exclusion)) {
                return false;
            }
        }

        return true;
    }


    /**
     * Check if this path matches a pattern
     *
     * @param string $path
     * @param string $pattern
     * @return boolean
     */
    protected function pathMatches(string $path, string $pattern): bool
    {
        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $path);
        }

        // Fallback
        return (bool)preg_match(
            "#^".strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.'])."$#i",
            $path
        );
    }


    /**
     * Returns a specific item by its relative path
     *
     * @param string $path
     * @return ItemInterface
     * @throws InvalidArgumentException If path is not in the collection.
     */
    public function itemByPath(string $path): ItemInterface
    {
        $base = $this->path;

        // Handle single-file disk collection
        if (is_file($base)) {
            $base = dirname($base);
            if ($path !== basename($this->path)) {
                throw new InvalidArgumentException("{$path} is not in this collection");
            }
        }

        return new DiskItem($base, $path);
    }
}
