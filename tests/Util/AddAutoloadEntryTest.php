<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Util\AddAutoloadEntry;

class AddAutoloadEntryTest extends TestCase
{
    private $jsonStr = <<<JSON
{
    "name": "vendor-name/test-project",
    "require": {},
    "prefer-stable": true,
    "minimum-stability": "dev"
}
JSON;


    /**
     * @internal checkPrerequisites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testUpgrade()
    {
        // Initial setup
        $root = vfsStream::setup('ss_project_root', null, [
            'composer.json' => $this->jsonStr
        ]);
        $code = new DiskCollection($root->url());

        // Apply basic namespace
        $autoload = new AddAutoloadEntry(
            $root->url(),
            $root->url() . '/mysite/code',
            'VendorName\\ProjectTest',
            false
        );
        $changeSet = $autoload->upgrade($code);
        $code->applyChanges($changeSet);
        $json = json_decode($code->itemByPath('composer.json')->getContents(), true);
        $this->assertEquals(
            ['psr4' => ['VendorName\\ProjectTest\\' => 'mysite/code/']],
            $json['autoload']
        );

        // Apply dev namespace
        $autoload = new AddAutoloadEntry(
            $root->url(),
            $root->url() . '/mysite/tests',
            'VendorName\\ProjectTest\\Tests',
            true
        );
        $changeSet = $autoload->upgrade($code);
        $code->applyChanges($changeSet);
        $json = json_decode($code->itemByPath('composer.json')->getContents(), true);
        $this->assertEquals(
            ['psr4' => ['VendorName\\ProjectTest\\' => 'mysite/code/']],
            $json['autoload']
        );
        $this->assertEquals(
            ['psr4' => ['VendorName\\ProjectTest\\Tests\\' => 'mysite/tests/']],
            $json['autoload-dev']
        );

        // Apply additional namespace
        $autoload = new AddAutoloadEntry(
            $root->url(),
            $root->url() . '/submodule/code',
            'VendorName\\SubModule',
            false
        );
        $changeSet = $autoload->upgrade($code);
        $code->applyChanges($changeSet);
        $json = json_decode($code->itemByPath('composer.json')->getContents(), true);
        $this->assertEquals(
            ['psr4' => [
                'VendorName\\ProjectTest\\' => 'mysite/code/',
                'VendorName\\SubModule\\' => 'submodule/code/'
            ]],
            $json['autoload']
        );
        $this->assertEquals(
            ['psr4' => ['VendorName\\ProjectTest\\Tests\\' => 'mysite/tests/']],
            $json['autoload-dev']
        );
    }
}
