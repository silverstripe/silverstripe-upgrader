<?php
namespace SilverStripe\Upgrader\Util;

use M1\Env\Parser;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;

/**
 * Load an existing `.env` file and provide a way for merging it with a new set of value.
 *
 * Loads environment variables from .env files
 *
 */
class DotEnvLoader
{

    /**
     * Full path to an env file that is read (if available). That's also where the results will be written.
     * @var string
     */
    private $envFilePath;

    /**
     * Existing content of the .env file.
     * @var string
     */
    private $inputContent = '';

    /**
     * Content that will be outputted to the .env file.
     * @var string
     */
    private $outputContent;

    /**
     * Const used to generate new the new file.
     * @var array
     */
    private $consts = [];

    /**
     * @param string $envFilePath File Path to `.env` file
     * @param array  $consts List of variables that should be added to envrionment file.
     */
    public function __construct(string $envFilePath)
    {
        $this->envFilePath = $envFilePath;

        $this->inputContent = $this->readFile($this->envFilePath);
        $this->consts = $this->parseFile($this->inputContent);

        $this->outputContent = $this->inputContent;
    }

    /**
     * Get a Code Change set that can be displayed back to the user.
     * @return CodeChangeSet
     */
    public function buildCodeChangeSet()
    {
        $changeSet = new CodeChangeSet();
        $changeSet->addFileChange($this->envFilePath, $this->getOutputContent(), $this->getInputContent());
        return $changeSet;
    }

    /**
     * Write the updated environment file.
     */
    public function writeChange()
    {
        file_put_contents($this->envFilePath, $this->getOutputContent());
    }

    /**
     * Accessor to get the current content of the `.env` file.
     * @return string
     */
    public function getInputContent()
    {
        return $this->inputContent;
    }

    /**
     * Accessor to get the content that will be written to the output .env file.
     * @return string
     */
    public function getOutputContent()
    {
        return $this->outputContent;
    }

    /**
     * Override existing const with new values.
     * @param  array $const
     */
    public function apply(array $consts)
    {
        $this->consts = array_merge($this->consts, $consts);
        $this->buildOutput();
    }

    /**
     * Read the existing .env file if available
     * @param string $envFilePath
     * @return string
     */
    private function readFile(string $envFilePath)
    {
        if (file_exists($envFilePath) && is_readable($envFilePath)) {
            return file_get_contents($envFilePath);
        } else {
            return '';
        }
    }


    /**
     * Parse the content of the .env file into an array and merge it newly provided array.
     * @param array $inputConsts New constant that should override existing constants read from the ``.env` file.
     * @return array
     */
    private function parseFile(string $content)
    {
        $const = Parser::parse($content);
        return $const;
    }

    /**
     * Converts a list of constant to a string suitable for output to a ``.env` file.
     * @param  array $outputConsts
     */
    private function buildOutput()
    {
        $content = '';
        foreach ($this->consts as $key => $val) {
            $content .= $key . "=\"" . addslashes($val) . "\"\n";
        }

        $this->outputContent = $content;
    }
}
