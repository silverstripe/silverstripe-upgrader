<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use org\bovigo\vfs\vfsStream;

class ChangeApplierTest extends TestCase
{

    private $fsStructure = [
        'FolderToMove' => [
            'cat.txt' => 'Meow!',
        ],
        'FolderToDelete' => [
            'dog.txt' => 'Woof',
        ],
        'fileToDelete.txt' => 'This file will be deleted',
        'fileToModify.txt' => 'old content',
        'fileToMove.txt' => 'This content will not change',
        'fileToMoveAndModify.txt' => 'old content',
        'fileToMoveToDir.txt' => 'This content will not change',
    ];

    private $expectedNewStructure = [
        'FolderMoved' => [
            'cat.txt' => 'Meow!',
            'brandNewFile.txt' => 'new content'
        ],
        'fileToModify.txt' => 'new content',
        'fileMoved.txt' => 'This content will not change',
        'fileMovedAndModified.txt' => 'new content',
        'NewFolder' => [
            'fileMovedToDir.txt' => 'This content will not change',
        ],
        'AnotherNewFolder' => [
            '.htaccess' => 'new content'
        ],
    ];

    protected function fixture()
    {
        $diff = new CodeChangeSet();
        $diff->move('FolderToMove', 'FolderMoved');
        $diff->remove('FolderToDelete');
        $diff->remove('fileToDelete.txt');
        $diff->addFileChange('fileToModify.txt', 'new content', 'old content');
        $diff->move('fileToMove.txt', 'fileMoved.txt');
        $diff->addFileChange(
            'fileToMoveAndModify.txt',
            'new content',
            'old content',
            'fileMovedAndModified.txt'
        );
        $diff->move('fileToMoveToDir.txt', 'NewFolder/fileMovedToDir.txt');
        $diff->addFileChange('FolderMoved/brandNewFile.txt', 'new content', null);
        $diff->addFileChange('AnotherNewFolder/.htaccess', 'new content', null);

        return $diff;
    }

    public function testApplyChange()
    {
        $root = vfsStream::setup('ss_project_root', null, $this->fsStructure);
        $disk = new DiskCollection($root->url(), true);
        $disk->applyChanges($this->fixture());

        $updated = vfsStream::inspect(new vfsStreamStructureVisitor(), $root)->getStructure();
        $this->assertEquals(
            $this->expectedNewStructure,
            $updated['ss_project_root'],
            'After applying changes, the directory structure should match `$expectedNewStructure`'
        );
    }
}
