<?php
namespace SilverStripe\Upgrader\Util;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\Composer\ComposerFile;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AddAutoloadEntry
 */
class AddAutoloadEntry
{
    /** @var string $rootPath */
    private $rootPath;

    /** @var string $filePath */
    private $filePath;

    /** @var string $namespace */
    private $namespace;

    /** @var bool $devPath */
    private $devPath;

    /**
     *
     * @param string $rootPath Root of the project or module where the composer file is.
     * @param string $filePath Path where namespace are to be added.
     * @param string $namespace Namespace of the file.
     * @param bool $devPath Whether this is a dev autoload entry or a regular entry.
     */
    public function __construct(string $rootPath, string $filePath, string $namespace, bool $devPath)
    {
        $this->rootPath = $rootPath;
        $this->filePath = $filePath;
        $this->namespace = rtrim($namespace, '\\') . '\\';
        $this->devPath = $devPath;
    }

    /**
     * Update a composer file to include a PSR-4 autoload entry.
     * @param CollectionInterface $code
     * @throws InvalidArgumentException if no composer file is present in the provided code collection.
     * @return CodeChangeSet
     */
    public function upgrade(CollectionInterface $code): CodeChangeSet
    {
        $jsonStr = $code->itemByPath('composer.json')->getContents();
        $json = json_decode($jsonStr, true);
        $json = $this->addAutoloadKey($json);
        return $this->toChangeSet($json, $jsonStr);
    }

    /**
     * Add an autoload key to a composer json array
     * @param array $json
     * @return array
     */
    private function addAutoloadKey(array $json): array
    {
        $key = $this->devPath ? 'autoload-dev' : 'autoload';
        if (!isset($json[$key])) {
            $json[$key] = [];
        }

        if (!isset($json[$key]['psr4'])) {
            $json[$key]['psr4'] = [];
        }


        $fs = new Filesystem();
        $path = $fs->makePathRelative($this->filePath, $this->rootPath);
        $json[$key]['psr4'][$this->namespace] = $path;

        return $json;
    }

    /**
     * Save the composer json array to a CodeChangeSet
     * @param array $json
     * @return CodeChangeSet
     */
    private function toChangeSet(array $json, string $original): CodeChangeSet
    {
        $jsonString = ComposerFile::encode($json);
        $changes = new CodeChangeSet();
        $changes->addFileChange('composer.json', $jsonString, $original);
        return $changes;
    }
}
