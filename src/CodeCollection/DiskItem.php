<?php

namespace SilverStripe\Upgrader\CodeCollection;

/**
 * Represents a simple file on disk.
 * Produced by DiskCollection
 */
class DiskItem implements ItemInterface
{
    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $relativePath;

    /**
     * Create a new DiskItem
     *
     * @param string $basePath Base directory name (not including trailing slash)
     * @param string $relativePath Path relative to base path
     */
    public function __construct($basePath, $relativePath)
    {
        // Validate an normalise inputs
        if (!$this->isWindows() && $basePath[0] !== '/') {
            throw new \InvalidArgumentException("basePath must start with /");
        }
        if (substr($basePath, -1) === '/') {
            $basePath = substr($basePath, 0, -1);
        }
        if ($relativePath[0] === '/') {
            $relativePath = substr($relativePath, 1);
        }
        if (substr($relativePath, -1) === '/') {
            throw new \InvalidArgumentException("relativePath cannot end with / - it must point to a file");
        }

        $this->basePath = $basePath;
        $this->relativePath = $relativePath;
    }

    public function getFullPath()
    {
        return $this->basePath . '/' . $this->relativePath;
    }

    /**
     * Return the base path of the collection that this item is part of
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Return the relative path of this item
     *
     * @return string
     */
    public function getPath()
    {
        return $this->relativePath;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return basename($this->relativePath);
    }

    /**
     * Read the contents of this file
     *
     * @return string
     */
    public function getContents()
    {
        return file_get_contents($this->getFullPath());
    }

    /**
     * Update the contents of this file
     *
     * @param string $contents
     */
    public function setContents($contents)
    {
        file_put_contents($this->getFullPath(), $contents);
    }

    /**
     * @return boolean
     */
    private function isWindows()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }
}
