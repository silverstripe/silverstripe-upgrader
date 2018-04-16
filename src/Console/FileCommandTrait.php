<?php
namespace SilverStripe\Upgrader\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait to encapsualte basic file access logic for Commands. Expects to be added to a subsclass of
 * {@link AbstractCommand}.
 */
trait FileCommandTrait
{
    /**
     * Get root path from the command input.
     *
     * @param InputInterface $input
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getRootPath(InputInterface $input): string
    {
        $rootPathArg = $input->getOption('root-dir');
        $rootPath = $this->realPath($rootPathArg);
        if (!is_dir($rootPath)) {
            throw new InvalidArgumentException("No silverstripe project found in root-dir \"{$rootPathArg}\"");
        }
        return $rootPath;
    }

    /**
     * Get path to files to upgrade
     *
     * @param InputInterface $input
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getFilePath(InputInterface $input): string
    {
        $filePathArg = $input->getArgument('path');
        $filePath = $this->realPath($filePathArg);
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("path \"{$filePathArg}\" specified doesn't exist");
        }

        // Find module name
        $rootPath = $this->getRootPath($input);
        if (($filePath === $rootPath) || stripos($filePath, $rootPath) !== 0) {
            throw new InvalidArgumentException(
                "root-dir \"{$rootPath}\" is not a parent of the specified path \"{$filePath}\""
            );
        }
        return $filePath;
    }

    /**
     * Instanciate a Path InputArgument instance
     * @return InputArgument
     */
    protected function getPathInputArgument(): InputArgument
    {
        return new InputArgument(
            'path',
            InputArgument::REQUIRED,
            'The root path to your code needing to be upgraded. This must be a subdirectory of base path.'
        );
    }

    /**
     * Instanciate a Path InputArgument instance
     * @return InputOption
     */
    protected function getRootInputOption(): InputOption
    {
        return new InputOption(
            'root-dir',
            'd',
            InputOption::VALUE_REQUIRED,
            'Specify project root dir, if not the current directory',
            '.'
        );
    }

    /**
     * Instanciate a _Write Change_ InputOption instance.
     * @return InputOption
     */
    protected function getWriteInputOption(): InputOption
    {
        return new InputOption(
            'write',
            'w',
            InputOption::VALUE_NONE,
            'Actually write the changes, rather than merely displaying them'
        );
    }
}
