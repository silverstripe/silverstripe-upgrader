<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

class DiskCollectionTest extends TestCase
{
    private $pathToSampleFolder =
        __DIR__ . DIRECTORY_SEPARATOR .
        'fixtures' . DIRECTORY_SEPARATOR .
        'SampleCode';

    public function testIterateItems()
    {
        // Most simple scenario, without recursive flag and without exclusion list.
        $d = new DiskCollection($this->pathToSampleFolder, false);

        $names = [];
        foreach ($d->iterateItems() as $item) {
            $this->assertInstanceOf(ItemInterface::class, $item);
            $names[] = $item->getPath();
        }

        sort($names); // Order of item doesn't matter
        $this->assertEquals([
            'ExcludedBySuffix.php',
            'ExcludedRegardlessOfFirstLetter.php',
            'FoundByItemPath.php',
            'ShouldBeReturnedInSearch.php'
        ], $names);

        // Recursive without exclusion list
        $d = new DiskCollection($this->pathToSampleFolder, true);

        $names = [];
        foreach ($d->iterateItems() as $item) {
            $this->assertInstanceOf(ItemInterface::class, $item);
            $names[] = $item->getPath();
        }

        sort($names); // Order of item doesn't matter
        $this->assertEquals([
            'ExcludedBySuffix.php',
            'ExcludedRegardlessOfFirstLetter.php',
            'FoundByItemPath.php',
            'ShouldBeReturnedInSearch.php',
            'SubSampleFolder/ExcludedBySuffixEvenWhenRecursive.php',
            'SubSampleFolder/FoundOnlyWithRecursiveFlag.php',
        ], $names);

        // Recursive with exclusion list
        $d = new DiskCollection(
            $this->pathToSampleFolder,
            true,
            [
                '*/ExcludedBy*.php',
                '*/?xcludedRegardlessOfFirstLetter.php'
            ]
        );

        $names = [];
        foreach ($d->iterateItems() as $item) {
            $this->assertInstanceOf(ItemInterface::class, $item);
            $names[] = $item->getPath();
        }

        sort($names); // Order of item doesn't matter
        $this->assertEquals([
            'FoundByItemPath.php',
            'ShouldBeReturnedInSearch.php',
            'SubSampleFolder/FoundOnlyWithRecursiveFlag.php'
        ], $names);
    }

    public function testItemByPath()
    {
        $d = new DiskCollection($this->pathToSampleFolder);

        $item = $d->itemByPath('FoundByItemPath.php');

        $this->assertEquals($this->pathToSampleFolder . '/FoundByItemPath.php', $item->getFullPath());
    }

    public function testExists()
    {
        $d = new DiskCollection(
            $this->pathToSampleFolder,
            true,
            [
                '*/ExcludedBy*.php',
                '*/?xcludedRegardlessOfFirstLetter.php'
            ]
        );

        $this->assertTrue($d->exists('FoundByItemPath.php'), 'FoundByItemPath exist, should be true.');
        $this->assertFalse($d->exists('NotFound.php'), 'NotFound does not exist, should be false.');
        $this->assertFalse(
            $d->exists('ExcludedBySyffix.php'),
            'ExcludedBySyffix does exist but is excluded so it should be false.'
        );
    }

    public function testPathNotExcluded()
    {
        $d = new DiskCollection(
            $this->pathToSampleFolder,
            true,
            [
                '*/SampleCode*',
            ]
        );

        $this->assertTrue($d->exists('FoundByItemPath.php'), 'FoundByItemPath exist, should be true.');
    }
}
