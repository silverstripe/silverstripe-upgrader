<?php
namespace SilverStripe\Upgrader\Util;

use Composer\Semver\Semver;
use InvalidArgumentException;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Composer\ComposerInterface;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;

/**
 * Utility class to help switch to using the public webroot. Only use by the `webroot` command.
 */
class WebRootMover
{

    /**
     * Minimal version of recipe core to use the public web root.
     */
    const MIN_CORE = '1.1';

    /**
     * Composer constraint recipe core has to meet for this package to work.
     */
    const CORE_CONSTRAINT = '>=' . self::MIN_CORE;

    /**
     * Name of the public webroot folder
     */
    const PUBLIC = 'public';

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var ComposerInterface
     */
    private $composer;

    public function __construct(string $rootPath, ComposerInterface $composer)
    {
        $this->rootPath = $rootPath;
        $this->composer = $composer;
    }


    /**
     * Make sure the current project meet the prerequisites to be migrated to the public webroot:
     * * Using recipeCore ^1.1
     * * Does not have a public folder or the public folder is empty.
     *
     * @throws InvalidArgumentException
     */
    public function checkPrerequesites(): void
    {
        $packageInfo = $this->composer->show();
        $packageInfo = array_filter($packageInfo, function ($package) {
            return $package['name'] == SilverstripePackageInfo::RECIPE_CORE;
        });
        if (empty($packageInfo) ||
            !Semver::satisfies($packageInfo[0]['version'], self::CORE_CONSTRAINT)
        ) {
            throw new InvalidArgumentException(sprintf(
                'To use the public webroot, your project must be using %s 1.1 or higher.',
                SilverstripePackageInfo::RECIPE_CORE
            ));
        }


        $publicPath = $this->publicPath();
        if (file_exists($publicPath) &&
            (
                !is_dir($publicPath) ||
                count(scandir($publicPath)) > 2
            )
        ) {
            throw new InvalidArgumentException(
                'There\'s already a non empty `public` folder in your project root.'
            );
        }
    }

    /**
     * Make sure we have a valid public folder to begin with.
     * @internal Depends on `checkPrerequesites` having been run.
     * @param CodeChangeSet $diff
     */
    public function initialisePublicFolder(CodeChangeSet &$diff)
    {
    }

    /**
     * Move server configuration file (.htaccess, web.config) around.
     * @internal Depends on `initialisePublicFolder` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveServerConfigFile(CodeChangeSet &$diff)
    {
    }

    /**
     * Move the assets folder from root of project to public folder.
     * @internal Depends on `initialisePublicFolder` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveAssets(CodeChangeSet &$diff)
    {
    }

    /**
     * Move files that would normally have been provided by the installer.
     * @internal Depends on `initialisePublicFolder` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveInstallerFiles(CodeChangeSet &$diff)
    {
    }

    /**
     * Expose vendor folder.
     * @internal Depends on the CodeChangeSet having been applied.
     */
    public function exposeVendor()
    {
    }

    /**
     * Look at a legacy file and see if it can be moved/upgraded to a newer version.
     * @param CodeChange $diff
     * @param $original
     * @param $destination
     * @param $replaceWith
     * @param array $compareTo
     */
    private function replaceLegacyFile(
        CodeChange &$diff,
        $original,
        $destination,
        $replaceWith,
        $compareTo = []
    ) {
    }

    /**
     * Get absolute path to public folder.
     * @return string
     */
    private function publicPath(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . self::PUBLIC;
    }
}
