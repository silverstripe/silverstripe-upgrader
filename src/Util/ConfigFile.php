<?php

namespace SilverStripe\Upgrader\Util;

use Symfony\Component\Yaml\Yaml;

class ConfigFile
{
    /**
     * Standard file name
     */
    const NAME = '.upgrade.yml';

    /**
     * Load config from the given file
     *
     * @param string $path
     * @return array
     */
    public static function loadConfig($path)
    {
        // Load config file
        if (file_exists(realpath($path))) {
            return Yaml::parse(file_get_contents(realpath($path)));
        } else {
            return [];
        }
    }

    /**
     * Load all config for the project
     *
     * @param string $rootPath
     * @return array
     */
    public static function loadCombinedConfig($rootPath)
    {
        // Merge with any other upgrade spec in the top level
        $config = [];
        foreach (new ModuleIterator($rootPath) as $path) {
            $nextFile = $path . DIRECTORY_SEPARATOR . static::NAME;
            if (file_exists($nextFile)) {
                $nextConfig = static::loadConfig($nextFile);
                // Update module-relative paths to root-relative
                $nextConfig = static::rewritePaths($nextConfig, $path);
                // Merge
                $config = static::mergeConfig($config, $nextConfig);
            }
        }
        return $config;
    }


    /**
     * Write config to file
     *
     * @param string $path
     * @param array $config
     */
    public static function saveConfig($path, $config)
    {
        file_put_contents($path, Yaml::dump($config, 2, 2));
    }

    protected static function mergeConfig(array $left, array $right)
    {
        $merged = $left;
        foreach ($right as $key => $value) {
            // if non-associative, just merge in unique items
            if (is_numeric($key)) {
                if (!in_array($value, $merged)) {
                    $merged[] = $value;
                }
                continue;
            }

            // If not merged into left hand side, then simply assign
            if (!isset($merged[$key])) {
                $merged[$key] = $value;
                continue;
            }

            // Make sure both sides are the same type
            if (is_array($merged[$key]) !== is_array($value)) {
                throw new \InvalidArgumentException(
                    "Config option $key cannot merge non-array with array value."
                );
            }

            // If array type, then merge
            if (is_array($value)) {
                $merged[$key] = static::mergeConfig($merged[$key], $value);
                continue;
            }

            // If non array types, don't merge, but instead assert both values are set
            if ($merged[$key] !== $value) {
                throw new \InvalidArgumentException(
                    "Config option $key is defined with different values in multiple files."
                );
            }
        }

        return $merged;
    }

    protected static function rewritePaths($config, $path)
    {
        // Rewrite doctorTasks path to absolute paths
        if (isset($config['doctorTasks'])) {
            foreach ($config['doctorTasks'] as $class => $classPath) {
                $config['doctorTasks'][$class] = $path . '/' . $classPath;
            }
        }
        return $config;
    }
}
