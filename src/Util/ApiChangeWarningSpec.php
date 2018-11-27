<?php

namespace SilverStripe\Upgrader\Util;

use PhpParser\Node\Stmt\Class_;

/**
 * Defines a warning for a particular code use (e.g. a class or a method).
 *
 * @package SilverStripe\Upgrader\Util
 */
class ApiChangeWarningSpec
{
    /**
     * @var string String defining a class, method or property.
     */
    protected $symbol;

    /**
     * String to rewrite to
     *
     * @var string
     */
    protected $replacement;

    /**
     * Required visibility
     *
     * @var string
     */
    protected $visibility;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string URL to more details
     */
    protected $url = '';

    /**
     * Prevent a rule erroring twice
     *
     * @var bool
     */
    protected $invalidRuleShown = false;

    /**
     * @param string $symbol
     * @param array $spec Spec in array format
     */
    public function __construct($symbol, $spec)
    {
        $this->symbol = $symbol;
        if (isset($spec['message'])) {
            $this->setMessage($spec['message']);
        }
        if (isset($spec['url'])) {
            $this->setUrl($spec['url']);
        }
        if (isset($spec['replacement'])) {
            $this->setReplacement($spec['replacement']);
        }
        if (isset($spec['visibility'])) {
            $this->setVisibility($spec['visibility']);
        }
    }

    /**
     * @param String $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param String $message
     * @return String
     */
    public function setMessage($message)
    {
        return $this->message = $message;
    }

    /**
     * @return String
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * @return string
     */
    public function getReplacement()
    {
        return $this->replacement;
    }

    /**
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param string $visibility
     * @return $this
     */
    public function setVisibility(string $visibility)
    {
        $this->visibility = $visibility;
        return $this;
    }

    /**
     * @return int|null returns the BitMask flag for the visibility of this spec as used by PhpParser
     */
    public function getVisibilityBitMask()
    {
        $visibility = $this->getVisibility();

        switch ($visibility) {
            case 'private': return Class_::MODIFIER_PRIVATE;
            case 'protected': return Class_::MODIFIER_PROTECTED;
            case 'public': return Class_::MODIFIER_PUBLIC;
        }

        return null;
    }

    /**
     * @return String The message with other info set.
     */
    public function getFullMessage()
    {
        $msg = "{$this->symbol}: {$this->message}";
        if ($this->url) {
            $msg .= " ({$this->url})";
        }

        return $msg;
    }

    /**
     * Called if this rule is invalid
     *
     * @param string $error
     */
    public function invalidRule($error)
    {
        if ($this->invalidRuleShown) {
            return;
        }
        user_error($error, E_USER_WARNING);
        $this->invalidRuleShown = true;
    }

    /**
     * @param string $replacement
     * @return $this
     */
    public function setReplacement(string $replacement)
    {
        $this->replacement = $replacement;
        return $this;
    }
}
