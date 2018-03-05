<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

class DiskCollectionTest extends TestCase
{
    public function testIterateItems()
    {
        $d = new DiskCollection(
            __DIR__ . '/../../src/CodeCollection',
            true,
            [
                '*/Disk*.php',
                '*/?temInterface.php'
            ]
        );

        $names = [];
        foreach ($d->iterateItems() as $item) {
            $this->assertInstanceOf(ItemInterface::class, $item);
            $names[] = $item->getPath();
        }

        // Note: iterator order isn't predictable, so sort
        sort($names);

        $this->assertEquals([
            'ChangeApplier.php',
            'CodeChangeSet.php',
            'CollectionInterface.php',
        ], $names);
    }

    public function testItemByPath()
    {
        $d = new DiskCollection(__DIR__ . '/../../src/CodeCollection');

        $item = $d->itemByPath('CollectionInterface.php');

        $this->assertEquals(__DIR__ . '/../../src/CodeCollection/CollectionInterface.php', $item->getFullPath());
    }
}
