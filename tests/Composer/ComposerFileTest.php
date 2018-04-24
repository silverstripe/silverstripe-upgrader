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

}
