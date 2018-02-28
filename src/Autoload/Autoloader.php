<?php

namespace SilverStripe\Upgrader\Autoload;

/**
 * Generic autoloader
 */
interface Autoloader
{

    /**
     * Activate this autoloader
     */
    public function register();

    /**
     * Disable this autoloader
     */
    public function unregister();
}
