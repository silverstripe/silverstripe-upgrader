<?php
namespace SilverStripe\Upgrader\Composer;

use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Utility for interacting with the `composer` executable.
 */
class ComposerExec implements ComposerInterface
{

    const TEMP_SCHEMA_CONTENT = <<<EOF
{
    "name": "silverstripe-upgrader/temp-project",
    "description": "silverstripe-upgrader-temp-project",
    "license": "proprietary",
    "minimum-stability": "dev",
    "require": {},
    "prefer-stable": true
}
EOF
    ;

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
     * @var OutputInterface
     */
    protected $out;

    /**
     * Instantiate a new ComposerExec.
     * @param string          $workingDir     Main Path where the composer project reside.
     * @param string          $execPath       Path to the composer executable.
     * @param OutputInterface $out            Output that can be used to push message to the cli.
     * @param boolean         $suppressErrors Whatever errors should be suppress. If false, composer errors will be
     *                                        printed on the CLI. Defaults to `true`. This is meant for testing only.
     */
    public function __construct(
        string $workingDir,
        string $execPath = "",
        OutputInterface $out = null,
        bool $suppressErrors = true
    ) {
        $this->suppressErrors = $suppressErrors;
        $this->setExecPath($execPath);
        $this->workingDir = realpath($workingDir);
        $this->out = $out;
    }

    /**
     * Setter for the Composer executable path. Set to "" if you want to try to look for composer in your path.
     * @param string $execPath
     * @throws InvalidArgumentException If the composer executable can not be found.
     * @return void
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

        // We could not find a functional composer executable. Panic Time!
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
     * @return void
     */
    public function setWorkingDir(string $workingDir): void
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Getter for the working directory. This is where composer will look for the `composer.json` file by
     * default.
     * @return string
     */
    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    /**
     * Run an arbitrary composer command.
     *
     * `$cmd` must specify the command to run including any arguments.
     * `$opts` can be use to specify options to append to the command. They key needs to be the name of the option
     * (including any dashes). The value needs to be whatever value needs to specified for the option. For valueless
     * options, just leave the value blank.
     *
     * Once the command has been run an array will be returned with the following structure:
     * * `return` containing the last line of output from the composer command ;
     * * `output` containing an array of all the lines of output ;
     * * `exitCode` containing the
     * [exit code of the composer command](https://getcomposer.org/doc/03-cli.md#process-exit-codes).
     *
     *
     * @see http://php.net/manual/en/function.exec.php
     * @param  string  $cmd          Command to run including arguments (e.g. `require silverstripe/framework`).
     * @param  array   $opts         Arguments to append to the command (e.g.: ['--prefer-stable' => '']).
     * @param  boolean $showProgress Display a simple animation while the progress is running.
     * @return Process
     */
    protected function run(string $cmd, array $opts = [], bool $showProgress = false): Process
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
                ($val ? sprintf('=%s', escapeshellarg($val)) : '');
        }

        $process = new Process($statement);

        // Choose whatever to display a progress indicator or not.
        if ($showProgress && $this->out) {
            $process->start();
            $i = 0;
            while ($process->isRunning()) {
                // Add a dot to the output every 3 sec
                if ($i % 10 == 0) {
                    $this->out->write('.');
                }
                $i++;
                // Sleep for 1/10th of a second.
                usleep(100000);
            }
        } else {
            $process->run();
        }

        // Output errors.
        if ($process->isSuccessful() && !$this->suppressErrors && $this->out) {
            $out = $this->out instanceof ConsoleOutputInterface ?
                $this->out->getErrorOutput() :
                $this->out;
            $out->writeln(sprintf('<error>%s</error>', $process->getErrorOutput()));
        }

        return $process;
    }

    /**
     * Test if the given exec path is a valid composer executable.
     *
     * @internal Technically this only test this is an executable, not that it's composer.
     * @param  string $execPath
     * @return boolean
     */
    protected function testExecPath(string $execPath): bool
    {
        $process = new Process($execPath. " about");
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Validate a specific composer file. This will return false if run against a composer file next to an out of sync
     * `composer.lock`.
     * @param  string  $path           Path to our composer file. Leave blank if you want to validate the file in the
     *                                 working dir.
     * @param  boolean $throwException Whatever to throw an exception on invalid file. Default: false.
     * @throws RuntimeException If validation fails and $throwException is true.
     * @return boolean
     */
    public function validate(string $path = '', bool $throwException = false): bool
    {
        $process = $this->run(sprintf('validate %s', escapeshellarg($path)));

        if ($throwException && !$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return $process->isSuccessful();
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

        // Write our dummy content to our new temp file
        // composer init doesn't give us an option to add the `prefer-stable` flag.
        file_put_contents(
            $fullPath . DIRECTORY_SEPARATOR . 'composer.json',
            self::TEMP_SCHEMA_CONTENT
        );

        return new ComposerFile($this, $fullPath, true);
    }

    /**
     * Run a `composer require` command to install a new dependency.
     * @param  string  $package      Name of package to require (e.g.: `silverstripe/recipe-core`).
     * @param  string  $constraint   Constraint to apply on the package (e.g: `^1.0.0`).
     * @param  string  $workingDir   Path to the directory containing the `composer.json`. Defaults to this instance's
     *    $workingDir.
     * @param  boolean $showFeedback Write out some information about what is going on.
     * @return void
     */
    public function require(
        string $package,
        string $constraint = '',
        string $workingDir = '',
        bool   $showFeedback = false
    ):void {
        // Constrain our package to some version.
        if ($constraint) {
            $package .= ':"' . $constraint . '"';
        }

        $showFeedback = $showFeedback && $this->out;

        if ($showFeedback) {
            $this->out->write(sprintf(' * Requiring %s ', $package));
        }

        $process = $this->run(
            "require $package",
            [
                '--working-dir' => $workingDir,
                '--prefer-stable' => '',
                '--ignore-platform-reqs' => '',
            ],
            $showFeedback
        );

        if ($process->isSuccessful()) {
            if ($showFeedback) {
                $this->out->write(" <fg=green>\u{2714}</>\n");
            }
        } else {
            if ($showFeedback) {
                $this->out->write(" <fg=red>\u{2717}</>\n");
            }
        }
    }

    /**
     * Run a `composer update` command to install a new dependency.
     * @param  string  $workingDir   Path to the directory containing the `composer.json`. Defaults to this instance's
     *    $workingDir.
     * @param  boolean $showFeedback Write out some information about what is going on.
     * @throws RuntimeException If update fails.
     * @return void
     */
    public function update(
        string $workingDir = '',
        bool   $showFeedback = false
    ): void {
        $showFeedback = $showFeedback && $this->out;

        if ($showFeedback) {
            $this->out->write(' Trying to install dependencies ');
        }

        $process = $this->run(
            "update",
            [
                '--working-dir' => $workingDir,
                '--prefer-stable' => '',
                '--ignore-platform-reqs' => '',
            ],
            $showFeedback
        );

        if ($process->isSuccessful()) {
            if ($showFeedback) {
                $this->out->write(" <fg=green>\u{2714}</>\n");
            }
        } else {
            if ($showFeedback) {
                $this->out->write(" <fg=red>\u{2717}</>\n");
            }
            throw new RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * Remove a pacakge from the dependencies.
     * @param  string $package    Name of package to remove.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     * @return void
     */
    public function remove(string $package, string $workingDir = ''): void
    {
        $this->run(
            'remove ' . $package,
            [
                '--working-dir' => $workingDir,
            ]
        );
    }

    /**
     * Run a composer install on the working directory.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     * @return void
     */
    public function install(string $workingDir = ''): void
    {
        $this->run(
            'install',
            ['--working-dir' => $workingDir, '--ignore-platform-reqs' => '']
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
        $process = $this->run('show', ['--working-dir' => $workingDir, '--format' => 'json']);
        $data = json_decode($process->getOutput(), true);
        return isset($data['installed']) ? $data['installed'] : [];
    }

    /**
     * Get the current cache directory for composer.
     * @return string
     */
    public function getCacheDir(): string
    {
        $process = $this->run('config cache-dir', ['--global' => '']);
        $cacheDir = trim($process->getOutput());
        return (file_exists($cacheDir) && is_dir($cacheDir)) ?
            $cacheDir :
            '';
    }

    /**
     * @inheritdoc
     * @param string $workingDir
     * @throws RuntimeException If there's an error occurs while running the command.
     * @return void
     */
    public function expose(string $workingDir = ''): void
    {
        $process = $this->run('vendor-expose', ['--working-dir' => $workingDir]);
        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
    }
}
