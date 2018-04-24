<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Composer\ComposerExec;
use InvalidArgumentException;

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

}
