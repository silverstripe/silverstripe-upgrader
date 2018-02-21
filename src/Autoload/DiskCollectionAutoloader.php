<?php

namespace SilverStripe\Upgrader\Autoload;

use Generator;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\DiskItem;

/**
 * Provides autoloading for a disk collection.
 *
 * Note: This loader supports modification of the underlying collection after registration
 */
class DiskCollectionAutoloader implements Autoloader
{
    /**
     * List of disk collections
     *
     * @var DiskCollection[]
     */
    protected $collections = [];

    public function register()
    {
        spl_autoload_register($this->getAutoloader());
    }

    public function unregister()
    {
        spl_autoload_unregister($this->getAutoloader());
    }

    /**
     * Get autoloader
     *
     * @return array
     */
    protected function getAutoloader()
    {
        return [$this, 'loadClass'];
    }

    /**
     * Add a collection to the loader
     *
     * @param DiskCollection $collection
     * @return $this
     */
    public function addCollection(DiskCollection $collection)
    {
        $this->collections[] = $collection;
        $this->resetCache();
        return $this;
    }

    /**
     * @return DiskCollection[]
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * @param DiskCollection[] $collections
     * @return DiskCollectionAutoloader
     */
    public function setCollections(array $collections)
    {
        $this->collections = $collections;
        $this->resetCache();
        return $this;
    }

    /**
     * Attempt to autoload the given class
     *
     * @param string $class
     */
    public function loadClass($class)
    {
        $files = $this->getFiles();

        // Lazy-autoload in case PSR-2 isn't setup
        $expectedFilename = basename($class) . '.php';
        /** @var DiskItem[] $rest */
        $rest = [];
        foreach ($files as $file) {
            // Try to load files with matching basename first
            if (strcasecmp($expectedFilename, $file->getFilename()) !== 0) {
                $rest[] = $file;
            } elseif ($this->loadDiskItem($file, $class)) {
                // Load and quit if successful
                return;
            }
        }

        // Maybe one of the leftover files has this?
        foreach ($rest as $file) {
            if ($this->loadDiskItem($file, $class)) {
                return;
            }
        }
    }

    /**
     * @var array
     */
    protected $filesCache = null;

    /**
     * Get list of files as simple array (with caching)
     *
     * @return DiskItem[]
     */
    protected function getFiles()
    {
        // Update cache if unset
        if (!isset($this->filesCache)) {
            $this->filesCache = iterator_to_array($this->getFileIterator());
        }
        return $this->filesCache;
    }

    /**
     * Kill cache if modified
     */
    protected function resetCache()
    {
        $this->filesCache = null;
    }

    /**
     * Iterator of files
     *
     * @return Generator
     */
    protected function getFileIterator()
    {
        foreach ($this->getCollections() as $collection) {
            /** @var DiskItem $collectionItem */
            foreach ($collection->iterateItems() as $collectionItem) {
                if ($collectionItem->getExtension() === 'php') {
                    yield $collectionItem;
                }
            }
        }
    }

    /**
     * Autoload the given disk item
     *
     * @param DiskItem $file
     * @param string $class Class to test for
     * @return bool True if successful
     */
    protected function loadDiskItem(DiskItem $file, $class)
    {
        require_once($file->getFullPath());
        return class_exists($class, false);
    }
}
