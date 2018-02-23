<?php

namespace SilverStripe\Upgrader\Util;

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
        $this->message = $spec['message'];
        if (isset($spec['url'])) {
            $this->setUrl($spec['url']);
        }
        if (isset($spec['replacement'])) {
            $this->setReplacement($spec['replacement']);
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
