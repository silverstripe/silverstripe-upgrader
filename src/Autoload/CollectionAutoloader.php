<?php

namespace SilverStripe\Upgrader\Autoload;

use Generator;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Provides autoloading for a disk collection.
 *
 * Note: This loader supports modification of the underlying collection after registration
 */
class CollectionAutoloader implements Autoloader
{
    /**
     * List of disk collections
     *
     * @var CollectionInterface[]
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
     * @param CollectionInterface $collection
     * @return $this
     */
    public function addCollection(CollectionInterface $collection)
    {
        $this->collections[] = $collection;
        $this->resetCache();
        return $this;
    }

    /**
     * @return CollectionInterface[]
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * @param CollectionInterface[] $collections
     * @return $this
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
        /** @var ItemInterface[] $rest */
        $rest = [];
        foreach ($files as $file) {
            // Try to load files with matching basename first
            if (strcasecmp($expectedFilename, $file->getFilename()) !== 0) {
                $rest[] = $file;
            } elseif ($this->loadItem($file, $class)) {
                // Load and quit if successful
                return;
            }
        }

        // Maybe one of the leftover files has this?
        foreach ($rest as $file) {
            if ($this->loadItem($file, $class)) {
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
     * @return ItemInterface[]
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
            /** @var ItemInterface $collectionItem */
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
     * @param ItemInterface $file
     * @param string $class Class to test for
     * @return bool True if successful
     */
    protected function loadItem(ItemInterface $file, $class)
    {
        require_once($file->getFullPath());
        return class_exists($class, false);
    }
}
