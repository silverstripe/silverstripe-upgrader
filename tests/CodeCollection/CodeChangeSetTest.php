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
}
