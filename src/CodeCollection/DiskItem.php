<?php

namespace SilverStripe\Upgrader\CodeCollection;

/**
 * Represents a simple file on disk.
 * Produced by DiskCollection
 */
class DiskItem implements ItemInterface
{

    private $basePath;
    private $relativePath;

    public function __construct($basePath, $relativePath)
    {
        // Validate an normalise inputs
        if ($basePath[0] !== '/') {
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

    /**
     * Return the full path of this item
     */
    public function getFullPath()
    {
        return $this->basePath . '/' . $this->relativePath;
    }

    /**
     * Return the base path of the collection that this item is part of
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Return the relative path of this item
     */
    public function getPath()
    {
        return $this->relativePath;
    }

    /**
     * Read the contents of this file
     * @return string
     */
    public function getContents()
    {
        return file_get_contents($this->getFullPath());
    }

    /**
     * Update the contents of this file
     * @param string $contents
     */
    public function setContents($contents)
    {
        file_put_contents($this->getFullPath(), $contents);
    }
}
