<?php

namespace SilverStripe\Upgrader\Composer;

use SilverStripe\Upgrader\CodeCollection\DiskItem;
use InvalidArgumentException;

/**
 * Represent a `composer.json` file and provide methods to interact with it. Note that this object doesn't
 * autoamtically detect changes to the composer file. So you may need to call the `parse` method after performing an
 * operation on this file to get the latest values.
 */
class ComposerFile extends DiskItem
{

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
     * Flag to indicate this is a temporary composer file that doesn't need to be retained after execution.
     * @var bool
     */
    protected $temporary;

    /**
     * Instanciate a new ComposerFile
     * @param ComposerExec $exec Composer executable to use when running operation on this file.
     * @param string $basePath Directory containing this composer file.
     * @param bool $temporaryProject Whatever this schema should be retain after execution.
     */
    public function __construct(ComposerExec $exec, string $basePath, bool $temporary = false)
    {
        parent::__construct($basePath, 'composer.json');
        $this->exec = $exec;
        $this->temporary = $temporary;
        $this->parse();
    }

    /**
     * Self-destruct the project if it's meant to be temporary.
     */
    public function __destruct()
    {
        if ($this->temporary) {
            $this->delDir($this->getBasePath());
        }
    }

    /**
     * Delete a directory and all of its content.
     * @internal PHP's `rmdir` thrown an error if you try to target constains other file. Copied from
     * https://stackoverflow.com/a/3349792/1427439
     * @param string $dirPath
     */
    private function delDir(string $dirPath)
    {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->delDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    /**
     * Try to parse the composer file content.
     * @throws InvalidArgumentException
     */
    public function parse()
    {
        if ($this->exec->validate($this->getFullPath())) {
            $this->composerJson = json_decode($this->getContents(), true);
        } else {
            throw new InvalidArgumentException('Invalid composer file at ' . $this->getFullPath());
        }
    }

    /**
     * Validate a composer file.
     * @param  array|string|null $content
     * @return boolean
     */
    public function validate($content = null)
    {
        // If we haven't been provided any content, assume we are validating the current file.
        if ($content === null) {
            $content = $this->composerJson;
        }

        // If we have an array, JSON code it
        if (is_array($content)) {
            // This is a bit of silly hack. If require is empty it will be outputted. as an array. `composer validate`
            // expect it to be an object, so we just had a dummy key pair in there to make sure it always generated as
            // an object.
            if (isset($content['require']) && empty($content['require'])) {
                $content['require']['php'] = '*';
            }
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

    /**
     * Get the requirements as defined by the `require` key in the composer file.
     * @return array
     */
    public function getRequire(): array
    {
        return $this->composerJson['require'];
    }
}
