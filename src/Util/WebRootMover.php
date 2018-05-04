<?php
namespace SilverStripe\Upgrader\Util;

use InvalidArgumentException;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;

/**
 * Utility class to help switch to using the public webroot. Only use by the `webroot` command.
 */
class WebRootMover
{

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
        $this->rootPath();
        $this->composer = $composer;
    }


    /**
     * Make sure the current project meet the prerequisites to be migrated to the public webroot:
     * * Using recipeCore ^1.1
     * * Does not have a public folder or the public folder is empty.
     *
     * @throws InvalidArgumentException
     */
    public function checkPrerequesites()
    {

    }

    /**
     * Make sure we have a valid public folder to begin with.
     * @internal Depends on `checkPrerequesites` having been wrong.
     * @param CodeChangeSet $diff
     */
    public function initialisePublicFolder(CodeChangeSet &$diff)
    {

    }

    /**
     * Move server configuration file (.htaccess, web.config) around.
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
     */
    public function moveInstallerFiles()
    {

    }

    /**
     * Expose vendor folder.
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

}
