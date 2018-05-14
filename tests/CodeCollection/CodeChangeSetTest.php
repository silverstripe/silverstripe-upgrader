<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;

class CodeChangeSetTest extends TestCase
{

    public function testAddFileChange()
    {
        $diff = new CodeChangeSet();

        // Add some files.
        $diff->addFileChange('updatedContent.txt', 'foo', 'bar');
        $diff->addFileChange(
            'updatedContentAndMove.txt',
            'foo',
            'bar',
            'toAnotherLocation.txt'
        );
        $diff->addFileChange('move.txt', 'foo', 'foo', 'withoutChange.txt');


        // Validate the change have been recorded properly.
        $this->assertEquals(
            [
                'updatedContent.txt' => [
                    'new' => 'foo',
                    'old' => 'bar',
                    'path' => 'updatedContent.txt',
                ],
                'updatedContentAndMove.txt' => [
                    'new' => 'foo',
                    'old' => 'bar',
                    'path' => 'toAnotherLocation.txt',
                ],
                'move.txt' => [
                    'path' => 'withoutChange.txt',
                ],
            ],
            $diff->allChanges(),
            'allChanges should return the combination of changes punch in the codeChangeset'
        );

        // Validated the affected files have been recorded properly.
        $this->assertEquals(
            $diff->affectedFiles(),
            [ 'updatedContent.txt', 'updatedContentAndMove.txt',  'move.txt'],
            'Our files should have been marked as affected.'
        );

        // Make sure no warnings have been added.
        $this->assertFalse($diff->hasWarnings('updatedContent.txt'));

        // Check base changes for an updated content.
        $this->assertEquals(
            $diff->newContents('updatedContent.txt'),
            'foo',
            'New content should have been recorded.'
        );
        $this->assertEquals(
            $diff->oldContents('updatedContent.txt'),
            'bar',
            'Old content should have been recorded.'
        );
        $this->assertEquals(
            $diff->newPath('updatedContent.txt'),
            'updatedContent.txt',
            'Path should not have change for a content only change.'
        );

        // Check base changes for an updated and moved file.
        $this->assertEquals(
            $diff->newContents('updatedContentAndMove.txt'),
            'foo',
            'New content should have been recorded.'
        );
        $this->assertEquals(
            $diff->oldContents('updatedContentAndMove.txt'),
            'bar',
            'Old content should have been recorded.'
        );
        $this->assertEquals(
            $diff->newPath('updatedContentAndMove.txt'),
            'toAnotherLocation.txt',
            'Path should have been updated for a moved file'
        );

        // Check base changes for Moved file
        $this->assertFalse(
            $diff->newContents('move.txt'),
            'Moved only file should have false new content'
        );
        $this->assertFalse(
            $diff->oldContents('move.txt'),
            'Old content should have been set to false for a moved file.'
        );
        $this->assertEquals(
            $diff->newPath('move.txt'),
            'withoutChange.txt',
            'Path for move file should have been updated for a moved file'
        );
    }

    public function testMove()
    {
        $diff = new CodeChangeSet();

        $diff->move('move.txt', 'withoutChange.txt');

        // Validate the change have been recorded properly.
        $this->assertEquals(
            [
                'move.txt' => [
                    'path' => 'withoutChange.txt',
                ],
            ],
            $diff->allChanges(),
            'allChanges should have recorded the moved file'
        );

        // Validated the affected file has been recorded properly.
        $this->assertEquals(
            $diff->affectedFiles(),
            ['move.txt'],
            'Our move file should have been marked as affected.'
        );

        // Make sure no warnings have been added.
        $this->assertFalse($diff->hasWarnings('move.txt'));

        // Check base changes for Moved file
        $this->assertFalse(
            $diff->newContents('move.txt'),
            'Moved only file should have false new content'
        );
        $this->assertFalse(
            $diff->oldContents('move.txt'),
            'Old content should have been set to false for a moved file.'
        );
        $this->assertEquals(
            $diff->newPath('move.txt'),
            'withoutChange.txt',
            'Path for move file should have been updated for a moved file'
        );
    }

    public function testDelete()
    {
        $diff = new CodeChangeSet();

        $diff->remove('oldFile.txt');

        // Validate the change have been recorded properly.
        $this->assertEquals(
            [
                'oldFile.txt' => [
                    'path' => false
                ],
            ],
            $diff->allChanges(),
            'allChanges should have recorded the deleted file'
        );

        // Validated the affected file has been recorded properly.
        $this->assertEquals(
            $diff->affectedFiles(),
            ['oldFile.txt'],
            'Our deleted file should have been marked as affected.'
        );

        // Make sure no warnings have been added.
        $this->assertFalse($diff->hasWarnings('oldFile.txt'));

        // Check base changes for Deleted file
        $this->assertFalse(
            $diff->newContents('oldFile.txt'),
            'Deleted file should have false new content'
        );
        $this->assertFalse(
            $diff->oldContents('oldFile.txt'),
            'Old content should have been set to false for a moved file.'
        );
        $this->assertFalse(
            $diff->newPath('oldFile.txt'),
            'Path for deleted file should be false'
        );
    }

    protected function fixture()
    {
        $c = new CodeChangeSet();
        $c->addFileChange('test1.php', 'foo', 'fo');
        $c->addWarning('test1.php', 15, 'something fishy');
        $c->addWarning('test2.php', 20, 'something to do');
        $c->addFileChange('test3.php', 'bar', 'ba');
        $c->addFileChange('subdir/test3.php', 'baz', 'ba');

        $c->move('moveTo.txt', 'differentLocation.txt');
        $c->addFileChange('brandNewFile.txt', 'new content', false);
        $c->remove('removed-file.txt');
        $c->addFileChange('file-with-same-content.txt', 'no-change', 'no-change');

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
            'moveTo.txt',
            'brandNewFile.txt',
            'removed-file.txt',
            'file-with-same-content.txt',
        ], $c->affectedFiles());
    }

    public function testAllChanges()
    {
        $c = $this->fixture();

        $this->assertEquals([
            'test1.php' => ['old' => 'fo', 'new' => 'foo', 'path' => 'test1.php'],
            'test3.php' => ['old' => 'ba', 'new' => 'bar', 'path' => 'test3.php'],
            'subdir/test3.php' => ['old' => 'ba', 'new' => 'baz', 'path' => 'subdir/test3.php'],
            'moveTo.txt' => ['path' => 'differentLocation.txt'],
            'brandNewFile.txt' => ['new' => 'new content', 'old' => false, 'path' => 'brandNewFile.txt'],
            'removed-file.txt' => ['path' => false],
            'file-with-same-content.txt' => ['path' => 'file-with-same-content.txt'],
        ], $c->allChanges());
    }

    public function testHasNewContentsAndWarnings()
    {
        $c = $this->fixture();

        $this->assertTrue($c->hasNewContents('test1.php'));
        $this->assertTrue($c->hasNewContents('brandNewFile.txt'));
        $this->assertFalse($c->hasNewContents('test2.php'));
        $this->assertFalse($c->hasNewContents('moveTo.txt'));
        $this->assertFalse($c->hasNewContents('removed-file.txt'));
        $this->assertFalse($c->hasNewContents('file-with-same-content.txt'));
        $this->assertFalse($c->hasNewContents('unaffectedfile.txt'));


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

    public function testOpsByPath()
    {
        $c = $this->fixture();
        $this->assertEquals(
            'modified',
            $c->opsByPath('test1.php'),
            'test1.php has been modified'
        );

        $this->assertEquals(
            'new file',
            $c->opsByPath('brandNewFile.txt'),
            'brandNewFile is a new file'
        );

        $this->assertEquals(
            'renamed',
            $c->opsByPath('moveTo.txt'),
            'moveTo has been renamed'
        );

        $this->assertEquals(
            'deleted',
            $c->opsByPath('removed-file.txt'),
            'removed-file.txt has been deleted'
        );

        $this->assertEquals(
            '',
            $c->opsByPath('file-with-same-content.txt'),
            'file-with-same-content has the same content and has not been moved'
        );

        $this->assertEquals(
            '',
            $c->opsByPath('unaffected.txt'),
            'unaffected file doesn have any outstanding operation against it.'
        );
    }
}
