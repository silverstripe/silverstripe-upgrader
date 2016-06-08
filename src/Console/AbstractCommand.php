<?php

namespace SilverStripe\Upgrader\Console;

use Symfony\Component\Console\Command\Command;

class AbstractCommand extends Command
{

    /**
     * Get real path of user-specified path
     *
     * @param string $path
     * @return string
     */
    protected function realPath($path)
    {
        return realpath($this->mapPath($path));
    }

    /**
     * Fix ~ in paths
     *
     * @param string $path
     * @return string
     */
    protected function mapPath($path)
    {
        // Fix ~/ being ignored by php
        if ($path && getenv('HOME') && stripos($path, '~') === 0) {
            $path = getenv('HOME') . substr($path, 1);
        }
        return $path;
    }
}
