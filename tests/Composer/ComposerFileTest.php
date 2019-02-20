<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use SilverStripe\Upgrader\Composer\Rules\PhpVersion;
use SilverStripe\Upgrader\Tests\Composer\Rules\MockDevRule;
use SilverStripe\Upgrader\Tests\Composer\Rules\MockRule;
use Symfony\Component\Console\Exception\RuntimeException;

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

        $json = json_decode(file_get_contents(__DIR__ . '/fixture/composer.json'), true);
        $this->assertTrue(
            $file->validate($json),
            'ComposerFile should validate when provided a valid content array.'
        );

        $json['require'] = [];
        $json['require-dev'] = [];
        $this->assertTrue(
            $file->validate($json),
            'ComposerFile should validate even if require and require-dev are empty.'
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
        $composer->require('php', '*', '', false, false);
        $composer->require('phpunit/phpunit', '*', '', false, true);

        // Test parse and require after updating the composer file.
        $schema->parse();
        $this->assertEquals(
            $schema->getRequire(),
            ['php' => '*'],
            'Parsing an updated file should cause getRequire to get the latest value.'
        );
        $this->assertEquals(
            $schema->getRequireDev(),
            ['phpunit/phpunit' => '*'],
            'Parsing an updated file should cause getRequireDev to get the latest value.'
        );
    }

    public function testInvalidParse()
    {
        $this->expectException(RuntimeException::class);

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

    public function testUpgrade()
    {
        $initialContent = <<<EOF
{
    "name": "silverstripe/dummy",
    "type": "project",
    "description": "Add extra functionality to enhance CMS user collaboration",
    "license": "BSD-3-Clause",
    "require": {
        "php": "^5.2"
    },
    "prefer-stable": true,
    "minimum-stability": "dev"
}
EOF
        ;

        $expectedContent =  <<<EOF
{
    "name": "silverstripe/dummy",
    "type": "project",
    "description": "Add extra functionality to enhance CMS user collaboration",
    "license": "BSD-3-Clause",
    "require": {
        "php": ">=5.6",
        "silverstripe-upgrader/mock-rule": "~1.2.3"
    },
    "prefer-stable": true,
    "minimum-stability": "dev",
    "require-dev": {
        "silverstripe-upgrader/mock-dev-rule": "~1.2.3"
    }
}
EOF
        ;

        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $schema->setContents($initialContent);
        $schema->parse();

        // Run the most simple command on our simple schema and see if the results matches what we expect.
        $diff = $schema->upgrade([new PhpVersion(), new MockRule(), new MockDevRule()]);

        $this->assertEquals($diff->newContents($schema->getFullPath()), $expectedContent);
        $this->assertEquals($diff->oldContents($schema->getFullPath()), $initialContent);
    }

    public function testEncode()
    {
        $data = [
            'require' => [],
            'require-dev' => [],
            'extra' => [],
            'config' => [],
            'autoload' => [],
            'autoload-dev' => [],
            'repositories' => []
        ];

        $jsonStr = ComposerFile::encode($data);
        $expected = <<<JSON
{
    "require": {},
    "require-dev": {},
    "extra": {},
    "config": {},
    "autoload": {},
    "autoload-dev": {},
    "repositories": []
}
JSON;

        $this->assertEquals($expected, $jsonStr);
    }
}
