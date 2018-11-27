<?php

namespace SilverStripe\Upgrader\Tests;

use Exception;

/**
 * Helper for loading test fixtures
 */
trait FixtureLoader
{
    /**
     * loads fixtures for a path
     *
     * @param string $path
     * @return array Array with parameters first, and each following pair as input / output set
     * @throws Exception
     */
    public function loadFixture($path)
    {
        // Get fixture from the file
        $fixture = file_get_contents($path);

        // Split
        $parts = preg_split('/------+/', $fixture, -1);

        // Parse parameters
        $parameters = json_decode(array_shift($parts), true);
        if ($parameters === null || json_last_error()) {
            throw new Exception(json_last_error_msg() ?: 'Invalid fixture file');
        }

        // trim all following parts
        $parts = array_map('trim', $parts);

        // Add parameters back to first in array
        array_unshift($parts, $parameters);
        return $parts;
    }
}
