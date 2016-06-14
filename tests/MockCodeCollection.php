<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\ChangeApplier;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockCodeCollection implements CollectionInterface
{

    use ChangeApplier;

    /**
     * List of paths and contents
     *
     * @var array
     */
    protected $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function iterateItems()
    {
        foreach ($this->items as $path => $contents) {
            yield new MockCodeItem($this, $path);
        }
    }

    /**
     * Get contents for path
     *
     * @param string $path
     * @return string
     */
    public function getItemContent($path)
    {
        return $this->items[$path];
    }

    /**
     * @param string $path
     * @param string $contents
     */
    public function setItemContent($path, $contents)
    {
        $this->items[$path] = $contents;
    }

    /**
     * Returns a specific item by its relative path
     *
     * @param string $path
     * @return ItemInterface
     */
    public function itemByPath($path)
    {
        return new MockCodeItem($this, $path);
    }
}
