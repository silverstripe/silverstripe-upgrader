<?php

namespace SilverStripe\Upgrader\Util;

use PhpParser\Node;

// Original written by Nikita Popov
// Sourced from https://github.com/nikic/TypeUtil/blob/master/src/MutableString.php

/**
 * String that can be modified without invalidating offsets into it
 */
class MutableString
{
    /**
     * Original value
     * @var string
     */
    protected $string;

    /** Array of modification, each modification an array of
     * format [pos, len, newString]
     *
     * @var array
     */
    protected $modifications = [];

    /**
     * @param string $string
     */
    public function __construct($string)
    {
        $this->string = $string;
    }

    /**
     * Insert value at position
     *
     * @param int $pos Position to insert at
     * @param string $newString String to insert
     */
    public function insert($pos, $newString)
    {
        $this->modifications[] = [$pos, 0, $newString];
    }

    /**
     * Remove string at position
     *
     * @param int $pos Position to remove from
     * @param int $len Number of characters to remove
     */
    public function remove($pos, $len)
    {
        $this->modifications[] = [$pos, $len, ''];
    }

    /**
     * Replace a string at a given position
     *
     * @param int $pos Position to insert at
     * @param int $len Number of characters to replace
     * @param string $newString New value of string
     */
    public function replace($pos, $len, $newString)
    {
        $this->modifications[] = [$pos, $len, $newString];
    }

    public function indexOf($str, $startPos)
    {
        return strpos($this->string, $str, $startPos);
    }

    public function getOrigString()
    {
        return $this->string;
    }

    protected static function indentString($string, $indent)
    {
        $lines = explode(PHP_EOL, $string);
        return implode(PHP_EOL . $indent, $lines);
    }

    public function getModifiedString()
    {
        $result = '';
        $startPos = 0;
        foreach ($this->getSortedModifications() as list($pos, $len, $newString)) {
            $result .= substr($this->string, $startPos, $pos - $startPos);
            // find the current indent size by finding the spaces since the last new line
            $indent = false;
            if (preg_match('/([[:blank:]]+)$/', $result, $matches)) {
                $indent = $matches[1];
            }
            $indentedString = $indent ? static::indentString($newString, $indent) : $newString;
            $result .= $indentedString;
            $startPos = $pos + $len;
        }
        $result .= substr($this->string, $startPos);
        return $result;
    }

    /**
     * Sort modifications
     *
     * @return array
     */
    public function getSortedModifications()
    {
        $modifications = $this->modifications;
        usort($modifications, function ($a, $b) {
            // Sort based on start position
            if ($a[0] !== $b[0]) {
                return $a[0] - $b[0];
            }
            // Sort based on length (0 length = insert first)
            return $a[1] - $b[1];
        });
        return $modifications;
    }
}
