<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

class DiskItemTest extends TestCase
{
    private $md5_test_folder = [
        'unix.txt' => "The quick brown\nfox jumps over\nthe lazy dog\n",
        'windows.txt' => "The quick brown\r\nfox jumps over\r\nthe lazy dog\r\n",
        'old_mac.txt' => "The quick brown\rfox jumps over\rthe lazy dog\r",
    ];

    const unixHash = '183ec6ef8810b0f0bf6c2bb54af63d49';
    const windowsHash = 'cc31bac25616b607fad628b011e8548a';
    const macHash = '8f272593193cc15660add332d9b58066';

    public function testGetMd5Hash()
    {
        $root = vfsStream::setup('ss_project_root', null, $this->md5_test_folder);

        // Test a normal unix file
        $item = new DiskItem($root->url(), 'unix.txt');
        $this->assertEquals(
            self::unixHash,
            $item->getMd5Hash(false),
            'Did not get the expected hash.'
        );

        $this->assertEquals(
            self::unixHash,
            $item->getMd5Hash(true),
            'Did not get the expected hash.'
        );

        // Test a normal windows file
        $item = new DiskItem($root->url(), 'windows.txt');
        $this->assertEquals(
            self::windowsHash,
            $item->getMd5Hash(false),
            'Did not get the expected hash.'
        );

        $this->assertEquals(
            self::unixHash,
            $item->getMd5Hash(true),
            'Did not get the expected hash.'
        );


        // Test a normal classic mac file
        $item = new DiskItem($root->url(), 'old_mac.txt');
        $this->assertEquals(
            self::macHash,
            $item->getMd5Hash(false),
            'Did not get the expected hash.'
        );

        $this->assertEquals(
            self::unixHash,
            $item->getMd5Hash(true),
            'Did not get the expected hash.'
        );
    }
}
