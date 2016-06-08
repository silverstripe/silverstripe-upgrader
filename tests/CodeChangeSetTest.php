<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\CodeChangeSet;

class CodeChangeSetTest extends \PHPUnit_Framework_TestCase
{
    protected function fixture()
    {
        $c = new CodeChangeSet();
        $c->addFileChange('test1.php', 'foo', 'fo');
        $c->addWarning('test1.php', 15, 'something fishy');
        $c->addWarning('test2.php', 20, 'something to do');
        $c->addFileChange('test3.php', 'bar', 'ba');
        $c->addFileChange('subdir/test3.php', 'baz', 'ba');
        return $c;
    }

    public function testAffectedFiles()
    {
        $c = $this->fixture();

        $this->assertEquals([
            'test1.php',
            'test2.php',
            'test3.php',
            'subdir/test3.php',
        ], $c->affectedFiles());
    }

    public function testAllChanges()
    {
        $c = $this->fixture();

        $this->assertEquals([
            'test1.php' => ['old' => 'fo', 'new' => 'foo'],
            'test3.php' => ['old' => 'ba', 'new' => 'bar'],
            'subdir/test3.php' => ['old' => 'ba', 'new' => 'baz'],
        ], $c->allChanges());
    }

    public function testHasNewContentsAndWarnings()
    {
        $c = $this->fixture();

        $this->assertTrue($c->hasNewContents('test1.php'));
        $this->assertFalse($c->hasNewContents('test2.php'));


        $this->assertTrue($c->hasWarnings('test2.php'));
        $this->assertFalse($c->hasWarnings('test3.php'));
    }

    public function testNewContents()
    {
        $c = $this->fixture();

        $this->assertEquals('foo', $c->newContents('test1.php'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNewContentsException()
    {
        $c = $this->fixture();
        $c->newContents('test2.php');
    }

    public function testWarnings()
    {
        $c = $this->fixture();

        $this->assertEquals([
            '<info>test1.php:15</info> <comment>something fishy</comment>'
        ], $c->warningsForPath('test1.php'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWarningsException()
    {
        $c = $this->fixture();
        $c->warningsForPath('test3.php');
    }
}
