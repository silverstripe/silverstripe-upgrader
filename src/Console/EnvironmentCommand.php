<?php

namespace SilverStripe\Upgrader\Console;

use BadMethodCallException;
use InvalidArgumentException;
use SilverStripe\Upgrader\Util\ConfigFile;
use SilverStripe\Upgrader\Util\EnvParser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Upgrader\CodeCollection\DiskItem;


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
                $this->getRootInputOption()
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());
        $rootPath = $this->getRootPath($input);

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
            return;
        }

        // Load the env file into a parser
        try {
            $parser = new EnvParser($envFile, $rootPath);
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

        // Get constants from the file and get them added
        $consts = $parser->getSSFourEnv();
        // @TODO get comments
        $this->writeFile($consts, $rootPath);
        $output->writeln(".env file written.");

        return null;
    }

    /**
     * Try to find an SS3 environment file.
     * @internal The logic to find the file is adapted from
     * [Constants.php](https://github.com/silverstripe/silverstripe-framework/blob/3/core/Constants.php#L34). I don't
     * want to improve it too much to make sure it's as close as possible to the original.
     * @return DiskItem|null SS3 Environment file reference or null if could not be found.
     */
    private function findSS3EnvFile($path)
    {
        //check this dir and every parent dir (until we hit the base of the drive)
        // or until we hit a dir we can't read
        while (true) {
            //if it's readable, go ahead
            if (@is_readable($path)) {
                //if the file exists, then we include it, set relevant vars and break out
                if (file_exists($path . DIRECTORY_SEPARATOR . self::SS3_ENV_FILE)) {
                    return new DiskItem($path, self::SS3_ENV_FILE);
                }
            }
            else {
                //break out of the while loop, we can't read the dir
                return null;
            }
            if (dirname($path) == $path) {
                //break out if we get to the root of the device.
                return null;
            }
            //go up a directory
            $path = dirname($path);
        }
    }

    /**
     * Write a new .env file to the root of the project folder
     * @param  string[]  $consts List of constants. Key should be the name of the conts.
     * @param  string $path Folder where the file should be written.
     * @return void
     */
    private function writeFile(array $consts, string $path)
    {
        $content = '';
        foreach ($consts as $key => $val) {
            $content .= $key . ":\"" . addslashes($val) . "\"\n";
        }

        file_put_contents($path . DIRECTORY_SEPARATOR . '.env', $content);
    }
}
