<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ComposerExecTest extends TestCase
{

    public function testExecPath()
    {
        // We are assuming that our composer is in the path of the environment running our test here.
        // Which is kind of dirty.

        $composer = new ComposerExec(__DIR__);

        $this->assertEquals($composer->getExecPath(), 'composer', 'ComposerExec should default to using `composer`');

        // We'll give a fake executable to our script here. In a perfect world we would be testing to make sure
        // whatever executable we are giving the script is actually a valid version of composer.
        $composer->setExecPath('echo');
        $this->assertEquals($composer->getExecPath(), 'echo', 'ComposerExec should accept a valid executable');

        // Unsetting the value should reset it back to `composer`
        $composer->setExecPath('');
        $this->assertEquals(
            $composer->getExecPath(),
            'composer',
            'ComposerExec should default to `composer after being unset`'
        );
    }

    public function testWorkingDir()
    {
        $composer = new ComposerExec(__DIR__);
        $this->assertEquals(
            $composer->getWorkingDir(),
            __DIR__,
            'working dir should be initialise with the composer value.'
        );

        $composer->setWorkingDir(__DIR__ . '/fixture');
        $this->assertEquals(
            $composer->getWorkingDir(),
            __DIR__ . '/fixture',
            'Setter for working dir should update the value.'
        );
    }

    public function testInvalidExecPath()
    {
        $this->expectException(
            InvalidArgumentException::class,
            'Using a non existing executable should throw an error'
        );

        $composer = new ComposerExec(__DIR__);
        $composer->setExecPath('./someNonSensicalCommandThatDoesNotExist');
    }

    public function testValidate()
    {
        $composer = new ComposerExec(__DIR__);
        $this->assertTrue(
            $composer->validate(__DIR__ . '/fixture/composer.json'),
            'Validating a valid composer file should return true'
        );

        $this->assertFalse(
            $composer->validate(__DIR__ . '/fixture/invalid-composer.json'),
            'Validating an invalid composer file should return false'
        );

        $this->assertFalse(
            $composer->validate(__DIR__ . '/fixture/non-existent-composer.json'),
            'Validating an non-existent composer file should return false'
        );
    }

    public function testValidateException()
    {
        $this->expectException(RuntimeException::class);
        $composer = new ComposerExec(__DIR__);
        $composer->validate(__DIR__ . '/fixture/invalid-composer.json', true);
    }

    public function testInitTemporarySchema()
    {
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $this->assertInstanceOf(
            ComposerFile::class,
            $schema,
            'initTemporarySchema should have returned a composer file'
        );

        $this->assertTrue($schema->validate(), "initTemporarySchema should build a valid composer file");

        $this->assertEmpty(
            $schema->getRequire(),
            "initTemporarySchema should build a composer file without any requirements."
        );
    }

    public function testRequire()
    {
        // Initialise our test objects
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        // Add a couple dependencies.
        $composer->require('ext-json');
        $composer->require('php', '>=5.0');

        // Reload our composer schema from the updated file.
        $schema->parse();
        $require = $schema->getRequire();

        // Let's run some tests.
        $this->assertNotEmpty($require, 'Require should have some values after calling `composer require` on it.');
        $this->assertArrayHasKey('ext-json', $require, 'Require should have added a dependency on ext-json');
        $this->assertArrayHasKey('php', $require, 'Require should have added a dependency on php');
        $this->assertEquals($require['php'], '>=5.0', 'Require should have constrain php to `>=5.0`');

        // Let's test the working dir param with a brand new file.
        $altSchema = $composer->initTemporarySchema();
        $composer->require('php', '*', $altSchema->getBasePath());
        $altSchema->parse();
        $require = $altSchema->getRequire();
        $this->assertArrayHasKey(
            'php',
            $require,
            'Require should have added a dependency on php even when working on a different working dir'
        );
    }

    public function testInstall()
    {
        // Initialise our test objects
        $path = __DIR__ . DIRECTORY_SEPARATOR .
            'fixture' . DIRECTORY_SEPARATOR .
            'collaboration-recipe'. DIRECTORY_SEPARATOR;

        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();

        // Copy our schema and lock file to a temp folder.
        $schema->setContents(file_get_contents($path. 'composer.json'));
        copy($path. 'composer.lock', $schema->getBasePath() . DIRECTORY_SEPARATOR . 'composer.lock');

        $composer->install($schema->getBasePath());

        $this->assertDirectoryExists(
            $schema->getBasePath() . DIRECTORY_SEPARATOR . 'vendor',
            'Composer install shoudl have created a vendor folder'
        );
    }

    public function testRemove()
    {
        // Initialise our test objects
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        // Add a dependency then remove it staright away
        $composer->require('ext-json');
        $composer->remove('ext-json');

        // Reload our composer schema from the updated file.
        $schema->parse();
        $require = $schema->getRequire();

        // Let's run some tests.
        $this->assertArrayNotHasKey('ext-json', $require, 'ext-json should have been remove');

        // Let's test the working dir param with a brand new file.
        $altSchema = $composer->initTemporarySchema();
        $composer->require('ext-json', '', $altSchema->getBasePath());
        $composer->remove('ext-json', $altSchema->getBasePath());
        $altSchema->parse();
        $require = $altSchema->getRequire();
        $this->assertArrayNotHasKey(
            'ext-json',
            $require,
            'ext-json should have been removed when working on a different working dir'
        );
    }

    public function testShow()
    {
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $this->assertEmpty($composer->show(), 'show should return an empty array when there is no requriements');

        $composer->require('composer/semver', '1.4.0');
        $this->assertEquals(
            $composer->show(),
            [[
                "name" => "composer/semver",
                "version" => "1.4.0",
                "description" => "Semver library that offers utilities, version constraint parsing and validation."
            ]],
            'show should dependencies that have just been required.'
        );
    }

    public function testCacheDir()
    {
        $composer = new ComposerExec(__DIR__);

        $this->assertContains(
            $composer->getCacheDir(),
            [
                $_SERVER['HOME'] . '/.composer/cache',
                $_SERVER['HOME'] . '/.cache/composer',
            ],
            'Composer CacheDir is not in one of the expected location'
        );
    }


    public function testUpdate()
    {
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $schema->setContents(<<<EOF
{
    "name": "silverstripe-upgrader/temp-project",
    "description": "silverstripe-upgrader-temp-project",
    "license": "proprietary",
    "minimum-stability": "dev",
    "require": {"league/flysystem": "*"},
    "prefer-stable": true
}
EOF
        );

        // Try to run a composer update
        $composer->update();


        $installedDependencies = $composer->show();
        $filtered = array_filter($installedDependencies, function ($dep) {
            return $dep['name'] == "league/flysystem";
        });


        $this->assertNotEmpty(
            $filtered,
            'Composer update should have installed `league/flysystem` given our test composer.json file.'
        );
    }

    public function testUpdateFailure()
    {
        $this->expectException(
            RuntimeException::class,
            'Calling composer update on a file with a broken dependencies should throw an exception.'
        );
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $schema->setContents(<<<EOF
{
    "name": "silverstripe-upgrader/temp-project",
    "description": "silverstripe-upgrader-temp-project",
    "license": "proprietary",
    "minimum-stability": "dev",
    "require": {"silverstripe/package-that-does-not-exist": "*"},
    "prefer-stable": true
}
EOF
        );

        // Try to run a composer update
        $composer->update();
    }

    public function testExpose()
    {
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $schema->setContents(<<<EOF
{
    "name": "silverstripe-upgrader/temp-project",
    "description": "silverstripe-upgrader-temp-project",
    "license": "proprietary",
    "minimum-stability": "dev",
    "require": {"silverstripe/recipe-cms": "1.1"},
    "prefer-stable": true
}
EOF
        );

        // Download dependency and the vendor-expose plugin
        $composer->update();

        // Update will have implicitly run vendor-expose. Let's remove the resources folder.
        $resPath = $schema->getBasePath() . DIRECTORY_SEPARATOR . 'resources';
        $fs = new Filesystem();
        $fs->remove($resPath);

        // Make sure we go in without an `resources` folder.
        $this->assertFileNotExists(
            $resPath,
            'Need to make sure we do not have a resources folder before running our vendor-expose.'
        );

        // Run an expose to make sure it doesn't throw an exception on us.
        $this->assertNull(
            $composer->expose(),
            "Vendor expose should have returned null without throwing an exception."
        );

        $this->assertFileExists($resPath, 'Vendor expose should have created a `resources` folder. ');
    }

    public function testExposeFailure()
    {
        $this->expectException(RuntimeException::class);

        // Set up a dummy composer project
        $composer = new ComposerExec(__DIR__);
        $schema = $composer->initTemporarySchema();
        $composer->setWorkingDir($schema->getBasePath());

        $schema->setContents(<<<EOF
{
    "name": "silverstripe-upgrader/temp-project",
    "description": "silverstripe-upgrader-temp-project",
    "license": "proprietary",
    "minimum-stability": "dev",
    "require": {},
    "prefer-stable": true
}
EOF
        );

        // This should throw an exception because
        $composer->expose();
    }
}
