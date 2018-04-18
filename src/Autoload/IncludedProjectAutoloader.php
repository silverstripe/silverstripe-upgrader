<?php

namespace SilverStripe\Upgrader\Autoload;

use BadMethodCallException;
use InvalidArgumentException;

class IncludedProjectAutoloader implements Autoloader
{
    /**
     * Base path to project being autoloaded
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * Construct autoloader for a project path
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Currently loaded project autoloader
     *
     * @var callable
     */
    protected $projectLoader = null;

    public function register()
    {
        $ds = DIRECTORY_SEPARATOR;
        $composer = "{$this->basePath}{$ds}vendor{$ds}autoload.php";
        if (!file_exists($composer)) {
            throw new InvalidArgumentException("Base path does not have a vendor/autoload.php file available");
        }
        require_once($composer);

        // composer likes to pre-pend it's autoloader! Let's make it not do that
        $functions = spl_autoload_functions();
        $this->projectLoader = $functions[0];
        spl_autoload_unregister($this->projectLoader);
        spl_autoload_register($this->projectLoader);
    }

    public function unregister()
    {
        if (!$this->projectLoader) {
            throw new BadMethodCallException("Cannot unload autoloader before it's loaded");
        }
        spl_autoload_unregister($this->projectLoader);
    }
}
