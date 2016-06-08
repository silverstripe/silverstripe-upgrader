<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockCodeItem implements ItemInterface
{

    public function __construct($parent, $path)
    {
        $this->parent = $parent;
        $this->path = $path;
    }
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Read the contents of this file
     * @return string
     */
    public function getContents()
    {
        return $this->parent->items[$this->path];
    }

    /**
     * Update the contents of this file
     * @param string $contents
     */
    public function setContents($contents)
    {
        $this->parent->items[$this->path] = $contents;
    }
}
