<?php
namespace SilverStripe\Upgrader\Composer;

use InvalidArgumentException;

/**
 * Utility for interacting with the `composer` executable.
 */
class ComposerExec {

    /**
     * @var string
     */
    private $execPath;

    /**
     * @var string
     */
    protected $workingDir;

    /**
     * @var bool
     */
    protected $suppressErrors;

    public function __construct($workingDir, $execPath="", bool $suppressErrors = true)
    {
        $this->suppressErrors = $suppressErrors;
        $this->setExecPath($execPath);
        $this->workingDir = realpath($workingDir);
    }

    /**
     * Setter for the Composer executable path.
     * @param string $execPath
     * @throws InvalidArgumentException
     */
    public function setExecPath(string $execPath)
    {
        if ($execPath) {
            if ($this->testExecPath($execPath)) {
                $this->execPath = $execPath;
                return;
            }
        } else if ($this->testExecPath('composer')) {
            $this->execPath = 'composer';
            return;
        } else if ($this->testExecPath('composer.phar')) {
            $this->execPath = 'composer.phar';
            return;
        }

        throw new InvalidArgumentException('Could not find the composer executable.');
    }

    public function getExecPath(): string
    {
        return $this->execPath;
    }

    protected function run(string $cmd, $arg = [])
    {
        $statement = $this->execPath . ' ' .
            $cmd . ' ' .
            implode($arg, ' ') .
            ' --working-dir=' . $this->workingDir;

        if ($this->suppressErrors) {
            $statement .= ' 2>&1';
        }

        $return = exec($statement, $output, $exitCode);

        return [
            'return' => $return,
            'output' => $output,
            'exitCode' => $exitCode
        ];

    }

    /**
     * Test if the given exect path is for Composer.
     * @param  string $execPath
     * @return bool
     */
    protected function testExecPath(string $execPath): bool
    {
        exec(
            $execPath. " about" . ($this->suppressErrors ? " 2>&1" : ""),
            $output,
            $exitCode
        );
        return $exitCode === 0;
    }

    /**
     * Validate a specific file
     * @param  string $path [description]
     * @return bool
     */
    public function validate(string $path): bool
    {
        $response = $this->run('validate', [$path]);

        return $response['exitCode'] === 0;
    }

}
