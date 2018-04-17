<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\Util\LegacyEnvParser;
use SilverStripe\Upgrader\Util\DotEnvLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Upgrader\ChangeDisplayer;

/**
 * Command to convert a SilverStripe 3 `_ss_environment.php` to a SilverStripe 4 `.env` file.
 */
class EnvironmentCommand extends AbstractCommand
{
    use FileCommandTrait;

    /**
     * Name of the environement file in SilverStripe 3.
     * @var string
     */
    const SS3_ENV_FILE = '_ss_environment.php';

    protected function configure()
    {
        $this->setName('environment')
            ->setDescription('Migrate settings from `_ss_environment.php` to .env')
            ->setDefinition([
                $this->getRootInputOption(),
                $this->getWriteInputOption()
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootPath = $this->getRootPath($input);
        $write = $input->getOption('write');

        // Try to find an environment file. Quit if we don't
        $envFile = $this->findSS3EnvFile($rootPath);
        if ($envFile) {
            $output->writeln(sprintf(
                "Converting `%s` ",
                $envFile->getFullPath()
            ));
        } else {
            $output->writeln(sprintf(
                "Could not find any `%s` file. Skipping environement upgrade.",
                self::SS3_ENV_FILE
            ));
            return null;
        }

        // Load the env file into a parser
        try {
            $parser = new LegacyEnvParser($envFile, $rootPath);
        } catch (\Exception $ex) {
            $output->writeln("There was an error when parsing your `_ss_environment.php` file.");
            $output->writeln($ex->getMessage());
            return null;
        }

        // Test file to see if it's suitable
        if (!$parser->isValid()) {
            $output->writeln(
                "Your environment file contains unusual constructs. " .
                "Upgrader will try to convert it any way, but take time to validate the result."
            );
        }

        // Get constants from the legacy file
        $consts = $parser->getSSFourEnv();


        // Load any .env const and mixin our legacy value
        $dotEnvLoader = new DotEnvLoader($rootPath . DIRECTORY_SEPARATOR . '.env', $consts);

        //
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $dotEnvLoader->getCodeChangeSet());



        // Apply them to the project
        if ($write) {
            $dotEnvLoader->writeChange();
            $output->writeln("Changes have been saved to your `.env` file");
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }

        return null;
    }

    /**
     * Try to find an SS3 environment file.
     * @internal The logic to find the file is adapted from
     * [Constants.php](https://github.com/silverstripe/silverstripe-framework/blob/3/core/Constants.php#L34). I don't
     * want to improve it too much to make sure it's as close as possible to the original.
     *
     * @param string $path
     * @return DiskItem|null SS3 Environment file reference or null if could not be found.
     */
    private function findSS3EnvFile($path)
    {
        // Check this dir and every parent dir (until we hit the base of the drive)
        // or until we hit a dir we can't read
        while ($path && @is_readable($path)) {
            //if the file exists, then we include it, set relevant vars and break out
            if (file_exists($path . DIRECTORY_SEPARATOR . self::SS3_ENV_FILE)) {
                return new DiskItem($path, self::SS3_ENV_FILE);
            }
            // Break out if we get to the root of the device.
            $parentPath = dirname($path);
            if ($parentPath === $path) {
                return null;
            }
            // Go up a directory
            $path = $parentPath;
        }
        return null;
    }
}
