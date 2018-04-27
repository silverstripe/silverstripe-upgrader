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

    protected function run(string $cmd, $args = [])
    {
        // Specify a working directory if we don't have one already.
        if (empty($args['--working-dir'])) {
            $args['--working-dir'] = $this->workingDir;
        }

        $statement = $this->execPath . ' ' . $cmd;

        foreach ($args as $arg => $val) {
            $statement .=
                ' ' . $arg .
                ($val ? sprintf('="%s"', $val) : '');
        }

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
        $response = $this->run('validate ' . $path);

        return $response['exitCode'] === 0;
    }

    /**
     * @return ComposerFile
     */
    public function initTemporaryFile()
    {
        $tmpDir = sys_get_temp_dir();
        $folderName = uniqid('ss-upgrader-');
        $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $folderName;

        mkdir($fullPath);

        $reponse = $this->run(
            'init', [
                '--working-dir' => $fullPath,
                '--quiet' => '',
                '--name' => 'silverstripe-upgrader/temp-project',
                '--description' => 'silverstripe-upgrader-temp-project',
                '--license' => 'proprietary',
            ]);

        return new ComposerFile($this, $fullPath);
    }

    public function require(string $package, string $constraint = '', string $workingDir = '')
    {
        if ($constraint) {
            $package . ':' . $constraint;
        }

        $this->run(
            'require ' . $package, [
                '--working-dir' => $workingDir,
                // '--no-update' => '',
            ]);
    }

}
