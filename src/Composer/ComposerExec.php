<?php
namespace SilverStripe\Upgrader\Composer;

use InvalidArgumentException;

/**
 * Utility for interacting with the `composer` executable.
 */
class ComposerExec
{

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

    /**
     * Instanciate a new ComposerExec.
     * @param string  $workingDir Main Path where the composer project reside.
     * @param string  $execPath Path to the composer executable.
     * @param bool $suppressErrors Whatever errors should be supress. If false, composer errors will be printed on the
     * CLI. Defaults to `true`. (This is meant for testing only.)
     */
    public function __construct(
        string $workingDir,
        string $execPath = "",
        bool $suppressErrors = true
    ) {
        $this->suppressErrors = $suppressErrors;
        $this->setExecPath($execPath);
        $this->workingDir = realpath($workingDir);
    }

    /**
     * Setter for the Composer executable path. Set to "" if you want to try to look for composer in your path.
     * @param string $execPath
     * @throws InvalidArgumentException
     */
    public function setExecPath(string $execPath): void
    {
        if ($execPath) {
            // User wants to explicitly define the path to composer
            if ($this->testExecPath($execPath)) {
                $this->execPath = $execPath;
                return;
            }
        } elseif ($this->testExecPath('composer')) {
            // User wants to rely on the path and we found a `composer` executable.
            $this->execPath = 'composer';
            return;
        } elseif ($this->testExecPath('composer.phar')) {
            // User wants to rely on the path and we found a `composer.phar` executable.
            $this->execPath = 'composer.phar';
            return;
        }

        // We could not find a functional coposer executable. Panick Time!
        throw new InvalidArgumentException('Could not find the composer executable.');
    }

    /**
     * Getter for the path of the composer executable.
     * @return string
     */
    public function getExecPath(): string
    {
        return $this->execPath;
    }

    /**
     * Setter for the Working Directory.
     * @param string $workingDir
     */
    public function setWorkingDir(string $workingDir): void
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Getter for the Working Directory. This is where composer will look for the `composer.json` file by
     * default.
     * @return string
     */
    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    /**
     * Run an arbritary composer command.
     *
     * `$cmd` must specify the command to run including any arguments.
     * `$opts` can be use to specify options to append to the command. They key needs to be the name of the option
     * (including any dashes). The value needs to be whatever value needs to specified for the option. For valueless
     * options, just leave the value blank.
     *
     * Once the commad has been run an array will be returned with the following structure:
     * * `return` containing the last line of output from the composer command ;
     * * `output` containing an array of all the lines of output ;
     * * `exitCode` containing the
     * [exit code of the composer command](https://getcomposer.org/doc/03-cli.md#process-exit-codes).
     *
     *
     * @see http://php.net/manual/en/function.exec.php
     * @param  string $cmd Command to run including arguments. (e.g. `require silverstripe/framework`)
     * @param  array  $opts Arguments to append to the command (e.g.: ['--prefer-stable' => ''])
     * @return array
     */
    protected function run(string $cmd, $opts = []): array
    {
        // Specify a working directory if we don't have one already.
        if (empty($opts['--working-dir'])) {
            $opts['--working-dir'] = $this->workingDir;
        }

        // Build up ou statement that will be run.
        $statement = $this->execPath . ' ' . $cmd;

        // Append all the tops to the command
        foreach ($opts as $opt => $val) {
            $statement .=
                ' ' . $opt .
                ($val ? sprintf('="%s"', $val) : '');
        }

        // Redirect STDERR to suppress errors.
        if ($this->suppressErrors) {
            $statement .= ' 2>&1';
        }

        // Execute our command.
        $return = exec($statement, $output, $exitCode);

        return [
            'return' => $return,
            'output' => $output,
            'exitCode' => $exitCode
        ];
    }

    /**
     * Test if the given exec path is a valid composer executable.
     *
     * @internal Technically this only test this is an excutable, not that it's composer.
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
     * Validate a specific composer file.
     * @param  string $path Path to our composer file.
     * @return bool
     */
    public function validate(string $path): bool
    {
        $response = $this->run('validate ' . $path);
        return $response['exitCode'] === 0;
    }

    /**
     * Initialise a new empty composer file in a temporary folder. The file will be initialise with the bare minimum to
     * be valid. This project can use to rebuild a composer file from scratch.
     * @return ComposerFile
     */
    public function initTemporarySchema(): ComposerFile
    {
        // Create a new temporary folder
        $tmpDir = sys_get_temp_dir();
        $folderName = uniqid('ss-upgrader-');
        $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $folderName;
        mkdir($fullPath);

        $reponse = $this->run(
            'init',
            [
                '--working-dir' => $fullPath,
                '--quiet' => '',
                '--name' => 'silverstripe-upgrader/temp-project',
                '--description' => 'silverstripe-upgrader-temp-project',
                '--license' => 'proprietary',
                '--stability' => 'dev',
            ]
        );

        return new ComposerFile($this, $fullPath, true);
    }

    /**
     * Run a `composer require` command to install a new dependency.
     * @param  string $package Name of package to require e.g.: `silverstripe/recipe-core`
     * @param  string $constraint Constraint to apply on the package. e.g: `^1.0.0`.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     */
    public function require(string $package, string $constraint = '', string $workingDir = ''): void
    {
        // Constrain our pacakge to some version.
        if ($constraint) {
            $package .= ':"' . $constraint . '"';
        }

        $this->run(
            "require $package",
            [
                '--working-dir' => $workingDir,
                '--prefer-stable' => '',
            ]
        );
    }

    /**
     * Remove a pacakge from the dependencies.
     * @param  string $package Name of package to remove.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     */
    public function remove(string $package, string $workingDir = '')
    {
        $this->run(
            'remove ' . $package,
            [
                '--working-dir' => $workingDir,
            ]
        );
    }

    /**
     * Show installed dependencies and return them as an array. The return array follow the same convention as
     * `composer show`:
     * ```php
     * [
     *     [...],
     *     ["name" => "silverstripe/framework", "version" => "4.1.0", "description" => "The SilverStripe framework"],
     *     [...],
     * ];
     * ```
     *
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     * @return array[]
     */
    public function show(string $workingDir = ''): array
    {
        $response = $this->run('show', ['--working-dir' => $workingDir, '--format' => 'json']);
        $output = implode($response['output'], "\n");
        $data = json_decode($output, true);
        return isset($data['installed']) ? $data['installed'] : [];
    }
}
