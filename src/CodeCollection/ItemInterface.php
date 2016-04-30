<?php

namespace Sminnee\Upgrader\CodeCollection;

/**
 * Represents a single item in a collection of code files, i.e., one file
 */
interface ItemInterface
{
    /**
     * Return the relative path of this item
     */
    public function getPath();

    /**
     * Read the contents of this file
     * @return string
     */
    public function getContents();

    /**
     * Update the contents of this file
     * @param string $contents
     */
    public function setContents($contents);
}
