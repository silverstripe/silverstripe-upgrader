<?php
namespace SilverStripe\Upgrader\Console;

use InvalidArgumentException;
use SilverStripe\Upgrader\Util\ConfigFile;

/**
 * Trait to encapsulate basic logic for retrieving upgrading config.
 */
trait ConfigurableCommandTrait
{
    /**
     * Retrieve values from the YML config.
     * @param string $rootPath Path where to search for a YML config file
     * @param bool $required Throw exception if no config can be found.
     * @throws InvalidArgumentException
     * @return array
     */
    protected function getConfig(string $rootPath, bool $required = true): array
    {
        $config = ConfigFile::loadCombinedConfig($rootPath);
        if (!$config && $required) {
            throw new InvalidArgumentException(
                "No .upgrade.yml definitions found in modules on \"{$rootPath}\". " .
                "Please ensure you upgrade your SilverStripe dependencies before running this task."
            );
        }
        return $config;
    }
}
