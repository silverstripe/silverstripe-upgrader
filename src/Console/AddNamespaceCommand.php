<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeRule\PHP\AddNamespaceRule;
use SilverStripe\Upgrader\UpgradeSpec;
use SilverStripe\Upgrader\Util\AddAutoloadEntry;
use SilverStripe\Upgrader\Util\ConfigFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddNamespaceCommand extends AbstractCommand implements AutomatedCommand
{
    use FileCommandTrait;
    use AutomatedCommandTrait;

    protected function configure()
    {
        $this->setName('add-namespace')
            ->setDescription('Add a namespace to a file.')
            ->setDefinition([
                new InputArgument(
                    'namespace',
                    InputArgument::REQUIRED,
                    'Namespace to add.'
                ),
                $this->getPathInputArgument(),
                new InputOption(
                    'recursive',
                    'r',
                    InputOption::VALUE_NONE,
                    'Set to recursively namespace.'
                ),
                new InputOption(
                    'psr4',
                    'p',
                    InputOption::VALUE_NONE,
                    'When used with the recursive option, assume directories and namespaces are PSR-4 compliant.'
                ),
                new InputOption(
                    'autoload',
                    null,
                    InputOption::VALUE_NONE,
                    'Add a matching entry to the composer file\'s autoload key.'
                ),
                new InputOption(
                    'autoload-dev',
                    null,
                    InputOption::VALUE_NONE,
                    'Add a matching entry to the composer file\'s autoload-dev key.'
                ),
                $this->getRootInputOption(),
                $this->getWriteInputOption()
            ]);
    }

    /**
     * @inheritdoc
     * @param array $args
     * @return array
     */
    protected function enrichArgs(array $args): array
    {
        $args['--write'] = true;
        $args['--recursive'] = true;
        $args['path'] = $args['code-path'];
        return array_intersect_key(
            $args,
            array_flip([
                '--write',
                '--root-dir',
                '--recursive',
                'namespace',
                'path',
                '--psr4',
            ])
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());

        // Strip out root directory from file path
        $filePath = $this->getFilePath($input);
        $rootPath = $this->getRootPath($input);
        $writeChanges = !empty($settings['write']);
        $namespace = $settings['namespace'];
        $recursive = !empty($settings['recursive']);
        $psr4 = !empty($settings['psr4']);
        $autoload = !empty($settings['autoload']);
        $autoloadDev = !empty($settings['autoload-dev']);

        // Capture missing double-escape in CLI for namespaces. :)
        if (stripos($namespace, "\\") === false) {
            throw new \InvalidArgumentException("Namespace \"{$namespace}\" doesn't seem escaped properly");
        }

        if ($autoload && $autoloadDev) {
            throw new \InvalidArgumentException(
                "`autoload` and `autoload-dev` can not be used simultaneously."
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
                    'psr4' => $psr4,
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
        $this->setDiff($changes);

        // Update composer.json
        if ($autoload || $autoloadDev) {
            $rootCode = new DiskCollection($rootPath);
            $addAutoloadEntry = new AddAutoloadEntry($rootPath, $filePath, $namespace, $autoloadDev);
            $composerChange = $addAutoloadEntry->upgrade($rootCode);
        }

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);
        if (isset($composerChange)) {
            $display->displayChanges($output, $composerChange);
        }

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
            if (isset($rootCode) && isset($composerChange)) {
                $rootCode->applyChanges($composerChange);
            }
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
