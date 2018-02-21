<?php


namespace SilverStripe\Upgrader\Util;

use LogicException;
use Nette\DI\Container;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\Type\TypeCombinator;

/**
 * Helper container for bootstrapping PHPStan
 */
class PHPStanState
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
    protected $level = 7;

    /**
     * @var Container
     */
    protected $container = null;

    /**
     * Init the state
     */
    public function init()
    {
        // Bootstrap container
        $this->initContainer();

        // Init union types option from config
        $unionTypes = $this->getContainer()->parameters['checkUnionTypes'];
        TypeCombinator::setUnionTypesEnabled($unionTypes);
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Init container
     */
    protected function initContainer()
    {
        // Create container from factory
        $containerFactory = new ContainerFactory(getcwd());
        $files = $this->getConfigFiles($containerFactory);
        $this->container = $containerFactory->create(sys_get_temp_dir(), $files);
    }

    /**
     * Find base dir with vendor in it
     *
     * @return null|string
     */
    protected function findVendorBase()
    {
        $dir = null;
        $next = __DIR__;
        while ($next && $next !== $dir) {
            // Success
            if (is_dir($next . '/vendor')) {
                return $next;
            }
            // Get next
            $dir = $next;
            $next = dirname($dir);
        }

        // Failed to find vendor folder anywhere
        throw new LogicException("silverstripe/upgrader not installed properly");
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param int $level
     * @return $this
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * @param array $extensions
     * @return $this
     */
    public function setExtensions(array $extensions)
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * Get all config files to load for this state
     *
     * @param ContainerFactory $containerFactory
     * @return array
     */
    protected function getConfigFiles(ContainerFactory $containerFactory)
    {
        // Get extension config paths
        $vendorBase = $this->findVendorBase();
        $files = array_map(function ($filename) use ($vendorBase) {
            return $vendorBase . DIRECTORY_SEPARATOR . $filename;
        }, $this->getExtensions());

        // Add level config
        $levelConfig = sprintf('config.level%s.neon', $this->getLevel());
        $levelConfigPath = $containerFactory->getConfigDirectory() . DIRECTORY_SEPARATOR . $levelConfig;
        if (!is_file($levelConfigPath)) {
            throw new LogicException("Could not load level {$levelConfig}");
        }
        $files[] = $levelConfigPath;
        return $files;
    }
}
