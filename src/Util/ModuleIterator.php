<?php

namespace SilverStripe\Upgrader\Util;

use Generator;
use IteratorAggregate;
use Traversable;

class ModuleIterator implements IteratorAggregate
{
    /**
     * @var string
     */
    protected $path = null;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Files that signify a module should be included
     *
     * @var array
     */
    protected $files = [
        '_config',
        '_config.php',
        ConfigFile::NAME,
    ];

    /**
     * Iterate over all module paths
     *
     * @param string $rootPath
     * @return Generator
     */
    public function find($rootPath)
    {
        // Check all root modules
        foreach (glob("{$rootPath}/*", GLOB_ONLYDIR) as $path) {
            // Mysite base
            if (dirname($path) === 'mysite' || $this->isModule($path)) {
                yield $path;
            }
        }

        // Check vendor modules
        foreach (glob("{$rootPath}/vendor/*/*", GLOB_ONLYDIR) as $path) {
            if ($this->isModule($path)) {
                yield $path;
            }
        }
    }

    /**
     * Determine if this is a module that should be upgraded
     *
     * @param string $path
     * @return bool
     */
    protected function isModule($path)
    {
        foreach ($this->files as $file) {
            if (file_exists($path . '/' . $file)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return $this->find($this->path);
    }
}
