<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use InvalidArgumentException;

class ComposerFileTest extends TestCase
{

    public function testValidate()
    {
        // Initialise things
        $file = new ComposerFile(
            new ComposerExec(__DIR__ . '/fixture'),
            __DIR__ . '/fixture'
        );

        $this->assertTrue(
            $file->validate(),
            'ComposerFile should validate when initialise with a valid file and validate is called with null'
        );

        $this->assertTrue(
            $file->validate(file_get_contents(__DIR__ . '/fixture/composer.json')),
            'ComposerFile should validate when provided a valid content string.'
        );

        $this->assertTrue(
            $file->validate(json_decode(file_get_contents(__DIR__ . '/fixture/composer.json'), true)),
            'ComposerFile should validate when provided a valid content array.'
        );

        $this->assertFalse(
            $file->validate(file_get_contents(__DIR__ . '/fixture/invalid-composer.json')),
            'ComposerFile should not validate when provided an invalid content string.'
        );

        $this->assertFalse(
            $file->validate(json_decode(file_get_contents(__DIR__ . '/fixture/invalid-composer.json'), true)),
            'ComposerFile should not validate when provided an invalid content array.'
        );
    }

    public function testParseAndRequire()
    {
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $this->assertEmpty($schema->getRequire(), 'getRequire should be empty when run against an empty file.');
        $composer->require('php', '*');

        // Test parse and require after updating the composer file.
        $schema->parse();
        $this->assertEquals(
            $schema->getRequire(),
            ['php' => '*'],
            'Parsing an updated file should cause getRequire to get the latest value.'
        );
    }

    public function testInvalidParse()
    {
        $this->expectException(InvalidArgumentException::class);

        // Let's create a brand new composer file and then break it on purpose.
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();

        $schema->setContents(
            $schema->getContents() .
            ' some random text that will break our JSON file.'
        );


        // Parsing hte file now should throw an exception.
        $schema->parse();
    }
}
