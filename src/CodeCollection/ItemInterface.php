<?php

namespace SilverStripe\Upgrader\CodeCollection;

/**
 * Represents a single item in a collection of code files, i.e., one file
 */
interface ItemInterface
{
    /**
     * Return the relative path of this item
     *
     * @return string
     */
    public function getPath();

    /**
     * Gets the full path including base
     *
     * @return string
     */
    public function getFullPath();

    /**
     * Get file extension
     *
     * @return mixed
     */
    public function getExtension();

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename();

    /**
     * Read the contents of this file
     *
     * @return string
     */
    public function getContents();

    /**
     * Update the contents of this file
     *
     * @param string $contents
     * @return void
     */
    public function setContents(string $contents): void;

    /**
     * Get the MD5 hash of this file. Accepts a parameter to convert the EOL character to Unix formats before
     * calculating in case some files have been converted to use the Windows format.
     * @param boolean $normalizeEOL Convert the content of the file to use line feed to as end-of-line character.
     * @return string
     */
    public function getMd5Hash(bool $normalizeEOL = false): string;
}
