<?php

namespace SilverStripe\Upgrader\Tests\Util;

use SilverStripe\Upgrader\Util\ConfigFile;

class ConfigFileTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadConfig()
    {
        $expected = [
            'fileExtensions' => ['php'],
            'mappings' => [
                'MyClass' => "DummyNamespace\\MyClass",
                'AwesomeClass' => "AnotherNamespace\\AwesomeClass",
                'OldClass' => "TheNamespace\\NewClass",
                'DummyClass' => "DummyNamespace\\DummyClass",
                'AnotherDummyClass' => "Another\\DummyClass",
            ],
            'doctorTasks' => [
                'Module1\Module1Task' => __DIR__ . '/fixtures/valid/module1/src/Tasks/Module1Task.php',
                'Module2\Module2Task' => __DIR__ . '/fixtures/valid/module2/code/MigrateTask.php',
            ],
        ];
        $config = ConfigFile::loadCombinedConfig(__DIR__ . '/fixtures/valid');
        $this->assertEquals($expected, $config);
    }

    public function testCantMergeType()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Config option fileExtensions cannot merge non-array with array value.'
        );
        ConfigFile::loadCombinedConfig(__DIR__ . '/fixtures/invalid');
    }

    public function testCantRedeclare()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Config option MyClass is defined with different values in multiple files.'
        );
        ConfigFile::loadCombinedConfig(__DIR__ . '/fixtures/invalid2');
    }
}
