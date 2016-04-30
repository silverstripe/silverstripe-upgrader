<?php

namespace Sminnee\Upgrader\Tests;

use Sminnee\Upgrader\CodeCollection\CollectionInterface;
use Sminnee\Upgrader\CodeCollection\ChangeApplier;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockCodeCollection implements CollectionInterface
{

    use ChangeApplier;

    public $items;

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
     * Returns a specific item by its relative path
     * @return ItemInterface
     */
    public function itemByPath($path)
    {
        new MockCodeItem($this, $path);
    }
}
