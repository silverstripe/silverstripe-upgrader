<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

class DiskCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testIterateItems()
    {
        $d = new DiskCollection(__DIR__ . '/../../src/CodeCollection');

        $names = [];
        foreach ($d->iterateItems() as $item) {
            $this->assertInstanceOf('SilverStripe\Upgrader\CodeCollection\ItemInterface', $item);
            $names[] = $item->getPath();
        }

        $this->assertEquals([
            'ChangeApplier.php',
            'CollectionInterface.php',
            'DiskCollection.php',
            'DiskItem.php',
            'ItemInterface.php',
        ], $names);
    }

    public function testItemByPath()
    {
        $d = new DiskCollection(__DIR__ . '/../../src/CodeCollection');

        $item = $d->itemByPath('CollectionInterface.php');

        $this->assertEquals(__DIR__ . '/../../src/CodeCollection/CollectionInterface.php', $item->getFullPath());
    }
}
