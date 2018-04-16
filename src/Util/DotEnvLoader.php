<?php
namespace SilverStripe\Upgrader\Util;
use M1\Env\Parser;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;


/**
 * Load an existing `.env` file and provide a way for merging it with a new set of value.
 *
 * Loads environment variables from .env files
 *
 * @internal Adapted from `SilverStripe\Core\EnvironmentLoader`.
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
     * @var [type]
     */
    private $inputContent = '';

    /**
     * Content that will be outputted to the .env file.
     * @var [type]
     */
    private $outputContent;

    /**
     * @param string $envFilePath File Path to `.env` file
     * @param array  $consts List of variables that should be added to envrionment file.
     */
    public function __construct(string $envFilePath, array $consts)
    {
        $this->envFilePath = $envFilePath;

        $this->inputContent = $this->readFile();
        $outputConsts = $this->parseFile($consts);
        $this->outputContent = $this->buildOutput();
    }

    /**
     * Get a Code Change set that can be displayed back to the user.
     * @return CodeChangeSet
     */
    public function getCodeChangeSet()
    {
        $changeSet = new CodeChangeSet();
        $changeSet->addFileChange($this->envFilePath, $this->outputContent, $this->inputContent);
        return $changeSet;
    }

    public function writeChange()
    {
        file_put_contents($this->envFilePath, $this->outputContent);
    }

    /**
     * Read the existing .env file if available
     * @return string
     */
    private function readFile()
    {
        if (file_exists($this->envFilePath) && is_readable($this->envFilePath)) {
            return file_get_contents($this->envFilePath);
        } else {
            return '';
        }
    }


    /**
     * Parse the content of the .env file into an array and merge it newly provided array.
     * @param array $inputConsts New constant that should override existing constants read from the ``.env` file.
     * @return array
     */
    private function parseFile(array $inputConsts)
    {
        $const = Parser::parse($this->inputContent);
        $const = array_merge($const, $inputConsts);
        return $const;
    }

    /**
     * Converts a list of constant to a string suitable for output to a ``.env` file.
     * @param  array $outputConsts
     * @return string
     */
    private function buildOutput(array $outputConsts): string
    {
        $content = '';
        foreach ($outputConsts as $key => $val) {
            $content .= $key . "=\"" . addslashes($val) . "\"\n";
        }

        return $content;
    }
}
