<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use SilverStripe\Upgrader\Tests\Composer\MockComposer;
use SilverStripe\Upgrader\Util\WebRootMover;
use InvalidArgumentException;

class WebRootMoverTest extends TestCase
{

    /**
     * @var  vfsStreamDirectory
     */
    private $root;

    public function setUp()
    {
        $this->root = vfsStream::setup('ss_project_root');
    }

    /**
     * @internal checkPrerequesites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequesites()
    {
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $this->assertNull($mover->checkPrerequesites());

        // Test with another version of 1.x
        $composer->showOutput[0]['version'] = '1.2.3';
        $this->assertNull($mover->checkPrerequesites());

        // Test with a major upgrade version ... we're future proofing here.
        $composer->showOutput[0]['version'] = '2.0.0';
        $this->assertNull($mover->checkPrerequesites());

        // Test with an empty public folder
        vfsStream::newDirectory('public')->at($this->root);
        $this->assertNull($mover->checkPrerequesites());
    }

    public function testCheckPrerequesitesFailedNoRecipeCore()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $mover->checkPrerequesites();
    }

    public function testCheckPrerequesitesFailedRecipeCoreVersion()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.0.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $mover->checkPrerequesites();
    }

    /**
     * @internal checkPrerequesites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequesitesFailedPublicNotEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Test with a npn-empty public folder
        $mover = new WebRootMover($this->root->url(), $composer);
        $dir = vfsStream::newDirectory('public')->at($this->root);
        $dir->addChild(vfsStream::newFile('.htaccess')->setContent(''));

        $mover->checkPrerequesites();
    }
}
