<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeRule\PHP\AddNamespaceRule;
use SilverStripe\Upgrader\UpgradeSpec;
use SilverStripe\Upgrader\Util\ConfigFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddNamespaceCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('add-namespace')
            ->setDescription('Add a namespace to a file')
            ->setDefinition([
                new InputArgument(
                    'namespace',
                    InputArgument::REQUIRED,
                    'Namespace to add'
                ),
                new InputArgument(
                    'path',
                    InputArgument::REQUIRED,
                    'Filename of PHP file or directory to write to'
                ),
                new InputOption(
                    'recursive',
                    'r',
                    InputOption::VALUE_NONE,
                    'Set to recursively namespace'
                ),
                new InputOption(
                    'root-dir',
                    'd',
                    InputOption::VALUE_REQUIRED,
                    'Specify project root dir, if not the current directory',
                    '.'
                ),
                new InputOption(
                    'write',
                    'w',
                    InputOption::VALUE_NONE,
                    'Actually write the changes, rather than merely displaying them'
                )
            ]);
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());

        // Strip out root directory from file path
        $filePath = $this->realPath($settings['path']);
        $rootPath = $this->realPath($settings['root-dir']);
        $writeChanges = !empty($settings['write']);
        $namespace = $settings['namespace'];
        $recursive = !empty($settings['recursive']);

        // Sanity check input
        if (!is_dir($rootPath)) {
            $rootPath = $settings['root-dir'];
            throw new \InvalidArgumentException("No silverstripe project found in root-dir \"{$rootPath}\"");
        }
        if (!file_exists($filePath)) {
            $filePath = $settings['path'];
            throw new \InvalidArgumentException("path \"{$filePath}\" specified doesn't exist");
        }
        // Capture missing double-escape in CLI for namespaces. :)
        if (stripos($namespace, "\\") === false) {
            throw new \InvalidArgumentException("Namespace \"{$namespace}\" doesn't seem escaped properly");
        }
        // Validate rootpath
        if (($filePath === $rootPath) || stripos($filePath, $rootPath) !== 0) {
            throw new \InvalidArgumentException(
                "root-dir \"{$rootPath}\" is not a parent of the specified path \"{$filePath}\""
            );
        }

        // Find module name
        $module = $this->getModuleName($filePath, $rootPath);
        if (empty($module)) {
            throw new \InvalidArgumentException(
                "Could not find module name for path \"{$filePath}\""
            );
        }

        // Build spec; This could potentially be configured via yml
        $config = [
            'fileExtensions' => ['php'],
            'add-namespace' => [
                [
                    'namespace' => $namespace,
                    'path' => substr($filePath, strlen($rootPath)),
                    'skipClasses' => [
                        'Page',
                        'Page_Controller',
                        'PageController',
                    ],
                ],
            ],
        ];

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $spec = new UpgradeSpec([
            $namespacer
                ->withParameters($config)
                ->withRoot($rootPath)
        ]);

        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Load the code to be upgraded and run the upgrade process
        $output->writeln("Applying namespace to \"{$filePath}\" in module \"{$module}\"");
        $code = new DiskCollection($filePath, $recursive);
        $changes = $upgrader->upgrade($code);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);

        // Apply them to the project
        if ($writeChanges) {
            $configFile = $rootPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . ConfigFile::NAME;
            $count = $namespacer->saveMappings($configFile);
            if ($count) {
                $output->writeln("Adding {$count} files to rename map file \"{$configFile}\"");
                $output->writeln(
                    "You will need to run `upgrade-code upgrade` on your project " .
                    "to update references to all newly namespaced classes."
                );
            }
            $code->applyChanges($changes);
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }
    }

    /**
     * Get path to module root
     *
     * @param string $filePath Path being upgrade
     * @param string $rootPath Root dir
     * @return string Path to module root dir
     */
    protected function getModuleName($filePath, $rootPath)
    {
        $relativePath = trim(substr($filePath, strlen($rootPath)), DIRECTORY_SEPARATOR);

        // If not in vendor, just return the basename
        $base = strtok($relativePath, DIRECTORY_SEPARATOR);
        if ($base !== 'vendor') {
            return $base;
        }
        // Get first three levels
        return $base
            . DIRECTORY_SEPARATOR . strtok(DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . strtok(DIRECTORY_SEPARATOR);
    }
}
