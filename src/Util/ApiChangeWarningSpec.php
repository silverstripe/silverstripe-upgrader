<?php

namespace SilverStripe\Upgrader\Util;

/**
 * Defines a warning for a particular code use (e.g. a class or a method).
 *
 * @package SilverStripe\Upgrader\Util
 */
class ApiChangeWarningSpec {

    /**
     * @var string String defining a class, method or property.
     */
    protected $symbol;

    /**
     * @var String
     */
    protected $message;

    /**
     * @var String URL to more details
     */
    protected $url = '';

    /**
     * @param String $symbol
     * @param String $message
     */
    public function __construct($symbol, $message)
    {
        $this->symbol = $symbol;
        $this->message = $message;
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
     * @return String
     */
    public function getSymbol()
    {
        return $this->symbol;
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
}