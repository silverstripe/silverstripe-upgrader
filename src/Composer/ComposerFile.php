<?php

namespace SilverStripe\Upgrader\Composer;

use SilverStripe\Upgrader\CodeCollection\DiskItem;
use InvalidArgumentException;

/**
 * Represent a `composer.json` file and provide methods to interact with it.
 */
class ComposerFile extends DiskItem {

    use TemporaryFile;

    /**
     * Parsed content of a composer.json file
     * @var array
     */
    protected $composerJson = [
        "name" => "",
        "description" => "",
        "license" => "",
        "authors" => [ ],
        "require" => [ ],
        "require-dev" => [ ],
        "minimum-stability" => "dev",
        "prefer-stable" => true
    ];

    /**
     * @var ComposerExec
     */
    protected $exec;

    /**
     *
     * @param [type] $basePath     [description]
     * @param string $relativePath [description]
     */
    public function __construct(ComposerExec $exec, $basePath)
    {
        parent::__construct($basePath, 'composer.json');
        $this->exec = $exec;
        $this->parse();
    }

    /**
     * Try to parse the composer file content.
     */
    public function parse()
    {
        if ($this->exec->validate($this->getFullPath())) {
            $this->composerJson = json_decode($this->getContents(), TRUE);
        } else {
            throw new InvalidArgumentException('Invalid composer file at ' . $this->getFullPath());
        }
    }

    /**
     * Validate a composer file.
     * @param  array|string|null $content
     * @return boolean
     */
    public function validate($content=null)
    {
        // If we haven't been provided any content, assume we are validating the current file.
        if ($content === null) {
            $content = $this->composerJson;
        }

        // If we have an array, JSON code it
        if (is_array($content)) {
            $content = json_encode($content);
        }

        if (!is_string($content)) {
            var_dump($content);
            throw new InvalidArgumentException('$content must be of type string, array or null');
        }

        $this->writeTmpFile($content);
        $path = $this->getTmpFilePath();

        return $this->exec->validate($path);
    }

    public function getRequire()
    {
        return $this->composerJson['require'];
    }

}
