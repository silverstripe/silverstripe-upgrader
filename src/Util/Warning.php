<?php

namespace SilverStripe\Upgrader\Util;

/**
 * Represents a visitor warning on a certain code location in a file.
 *
 * @package SilverStripe\Upgrader\Util
 */
class Warning
{
    /**
     * @var string Absolute path to file
     */
    protected $path;

    /**
     * @var int Line in the referenced file
     */
    protected $line;

    /**
     * @var string Plaintext message
     */
    protected $message;

    public function __construct($path, $line, $message)
    {
        $this->path = $path;
        $this->line = $line;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
