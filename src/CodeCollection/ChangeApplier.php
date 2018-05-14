<?php
namespace SilverStripe\Upgrader\CodeCollection;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Generic implementation of CollectionInterface::applyChanges
 */
trait ChangeApplier
{
    /**
     * Returns a specific item by its relative path
     *
     * @param string $path
     * @return ItemInterface
     */
    abstract public function itemByPath(string $path): ItemInterface;

    /**
     * Apply the changes contained in the CodeChangeSet.
     * @param CodeChangeSet $changes
     * @return void
     */
    public function applyChanges(CodeChangeSet $changes): void
    {
        $fs = new Filesystem();

        foreach ($changes->allChanges() as $path => $change) {
            $ops = $changes->opsByPath($path);
            $item = $this->itemByPath($path);
            $fullPath = $item->getFullPath();
            switch ($ops) {
                case 'deleted':
                    $fs->remove($fullPath);
                    break;
                case 'renamed':
                    $this->createDirForPath($fs, $item->getBasePath(), $change['path']);
                    $fs->rename($fullPath, $item->getBasePath() . DIRECTORY_SEPARATOR . $change['path']);
                    $item = $this->itemByPath($change['path']);
                    break;
                case 'new file':
                    $this->createDirForPath($fs, $item->getBasePath(), $change['path']);
                    break;
            }

            if ($changes->hasNewContents($path)) {
                $item->setContents($change['new']);
            }
        }
    }

    /**
     * Make sure the provided path exists
     * @param FileSystem $fs
     * @param string $basePath
     * @param string $relativePath
     * @return void
     */
    private function createDirForPath(FileSystem $fs, string $basePath, string $relativePath): void
    {
        $paths = explode(DIRECTORY_SEPARATOR, $relativePath);
        array_pop($paths);
        if ($paths) {
            $fs->mkdir($basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths));
        }
    }
}
