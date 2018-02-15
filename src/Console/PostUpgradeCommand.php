<?php

namespace SilverStripe\Upgrader\Console;

use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use LogicException;
use Nette\DI\Container;
use PhpParser\NodeTraverser;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\Type\TypeCombinator;
use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\Upgrader;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeRewriteRule;
use SilverStripe\Upgrader\UpgradeSpec;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PostUpgradeCommand extends UpgradeCommand
{
    /**
     * PHPStan extensions
     *
     * @var array
     */
    protected $extensions = [
        'vendor/phpstan/phpstan-php-parser/extension.neon',
    ];

    /**
     * PHPStan level
     *
     * @var int
     */
    protected $level = 3;

    protected function configure()
    {
        $this->setName('post-upgrade')
            ->setDescription('Runs additional post-upgrade rewrites to tidy up loose ends')
            ->setDefinition([
                new InputArgument(
                    'path',
                    InputArgument::OPTIONAL,
                    'The root path to your code needing to be upgraded. Defaults to current directory.',
                    '.'
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
                    'Actually write the changes (to disk and to upgrade-spec), rather than merely displaying them'
                )
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Setup PHPStan
        $container = $this->buildContainer();
        TypeCombinator::setUnionTypesEnabled($container->parameters['checkUnionTypes']);

        // Post-upgrade requires additional composer autoload.php to work
        $this->enableProjectAutoloading($input);

        // Build spec
        $spec = new UpgradeSpec();
        $config = $this->getConfig($input);
        $spec->addRule((new ApiChangeRewriteRule($container))->withParameters($config));

        // Create upgrader with this spec
        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Build disc collection
        $filePath = $this->getFilePath($input);
        $exclusions = isset($config['excludedPaths']) ? $config['excludedPaths'] : [];
        $code = new DiskCollection($filePath, true, $exclusions);

        // Run upgrade
        $output->writeln("Running post-upgrade on \"{$filePath}\"");
        $changes = $upgrader->upgrade($code);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);
        $count = count($changes->allChanges());

        // Apply them to the project
        if ($input->getOption('write')) {
            $output->writeln("Writing changes for {$count} files");
            $code->applyChanges($changes);
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }
    }

    /**
     * Setup autoloading that loads project files
     *
     * @param InputInterface $input
     */
    protected function enableProjectAutoloading(InputInterface $input): void
    {
        // Setup base autoloading (psr-4 should cover this)
        $base = $this->getRootPath($input);
        $ds = DIRECTORY_SEPARATOR;
        $composer = "{$base}{$ds}vendor{$ds}autoload.php";
        if (!file_exists($composer)) {
            throw new InvalidArgumentException("Base path does not have a vendor/autoload.php file available");
        }
        require_once($composer);

        // composer likes to pre-pend it's autoloader! Let's make it not do that
        $functions = spl_autoload_functions();
        $projectLoader = $functions[0];
        spl_autoload_unregister($projectLoader);
        spl_autoload_register($projectLoader);

        // Setup custom autoloading for the given upgrade path
        $filePath = $this->getFilePath($input);
        $codeBase = new DiskCollection($filePath);
        $files = iterator_to_array($codeBase->iterateItems());

        // Lazy-autoload in case PSR-2 isn't setup
        spl_autoload_register(function($class) use ($files) {
            $baseName = basename($class);
            $rest = [];
            /** @var DiskItem $file */
            foreach ($files as $file) {
                // Skip non-php files
                $pathinfo = pathinfo($file->getFilename());
                if ($pathinfo['extension'] !== 'php') {
                    continue;
                }
                // Try to load files with matching basename first
                if (strcasecmp($baseName, $pathinfo['filename']) === 0) {
                    // Load and quit if successful
                    require_once($file->getFullPath());
                    if (class_exists($class, false)) {
                        return;
                    }
                } else {
                    $rest[] = $file;
                }
            }

            // Maybe one of the leftover files has this?
            /** @var DiskItem $file */
            foreach ($rest as $file) {
                // Load, and hopefully stop on success
                require_once($file->getFullPath());
                if (class_exists($class, false)) {
                    return;
                }
            }
        });
    }

    /**
     * @return Container
     */
    protected function buildContainer()
    {
        // Create factory
        $containerFactory = new ContainerFactory(getcwd());

        // Add extra config files
        $vendorBase = $this->findVendorBase();
        $files = array_map(function ($filename) use ($vendorBase) {
            return $vendorBase . DIRECTORY_SEPARATOR . $filename;
        }, $this->extensions);

        // Add level config
        $levelConfigFile = sprintf(
            '%s/config.level%s.neon',
            $containerFactory->getConfigDirectory(),
            $this->level
        );
        if (!is_file($levelConfigFile)) {
            throw new LogicException("Could not load level config.level{$this->level}.neon");
        }
        $files[] = $levelConfigFile;

        // Build container from factory
        return $containerFactory->create(sys_get_temp_dir(), $files);
    }

    /**
     * Find base dir
     *
     * @return null|string
     */
    protected function findVendorBase()
    {
        $dir = null;
        $next = __DIR__;
        while ($next && $next !== $dir) {
            // Success
            if (is_dir($next .'/vendor')) {
                return $next;
            }
            // Get next
            $dir = $next;
            $next = dirname($dir);
        }

        // Failed to find vendor folder anywhere
        throw new LogicException("silverstripe/upgrader not installed properly");
    }
}
