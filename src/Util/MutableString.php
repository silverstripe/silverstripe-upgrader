<?php

namespace Sminnee\Upgrader\Util;

use PhpParser\Node;

// Original written by Nikita Popov
// Sourced from https://github.com/nikic/TypeUtil/blob/master/src/MutableString.php

/**
 * String that can be modified without invalidating offsets into it
 */
class MutableString
{
    private $string;

    // [[pos, len, newString]]
    private $modifications = [];

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function insert($pos, $newString)
    {
        $this->modifications[] = [$pos, 0, $newString];
    }

    public function remove($pos, $len)
    {
        $this->modifications[] = [$pos, $len, ''];
    }

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

    public function getModifiedString()
    {
        // Sort by position
        usort($this->modifications, function ($a, $b) {
            return $a[0] - $b[0];
        });
        $result = '';
        $startPos = 0;
        foreach ($this->modifications as list($pos, $len, $newString)) {
            $result .= substr($this->string, $startPos, $pos - $startPos);
            $result .= $newString;
            $startPos = $pos + $len;
        }
        $result .= substr($this->string, $startPos);
        return $result;
    }
}
