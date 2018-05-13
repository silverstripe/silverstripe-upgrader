<?php
namespace SilverStripe\Upgrader\Composer;

use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Encapsulate the method that can be called on the composer executable.
 *
 * @internal This things mostly exists so we can mock ComposerExec.
 */
interface ComposerInterface
{

    /**
     * Validate a specific composer file.
     * @param  string  $path           Path to our composer file.
     * @param  boolean $throwException Whatever to throw an exception on invalid file. Default: false.
     * @throws RuntimeException If validation fails and $throwException is true.
     * @return boolean
     */
    public function validate(string $path, bool $throwException = false): bool;

    /**
     * Initialise a new empty composer file in a temporary folder. The file will be initialise with the bare minimum to
     * be valid. This project can use to rebuild a composer file from scratch.
     * @return ComposerFile
     */
    public function initTemporarySchema(): ComposerFile;

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
    ): void;

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
    ): void;

    /**
     * Remove a pacakge from the dependencies.
     * @param  string $package    Name of package to remove.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     * @return void
     */
    public function remove(string $package, string $workingDir = ''): void;

    /**
     * Run a composer install on the working directory.
     * @param  string $workingDir Path to the directory containing the `composer.json`. Defaults to this instance's
     * $workingDir.
     * @return void
     */
    public function install(string $workingDir = ''): void;

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
    public function show(string $workingDir = ''): array;

    /**
     * Get the current cache directory for composer.
     * @return string
     */
    public function getCacheDir(): string;

    /**
     * Call the custom silverstripe `vendor-expose` composer command.
     * @param string $workingDir
     * @throws RuntimeException If there's an error occurs while running the command.
     * @return void
     */
    public function expose(string $workingDir = ''): void;
}
