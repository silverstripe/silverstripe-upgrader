<?php
namespace SilverStripe\Upgrader\Util;

use Composer\Semver\Semver;
use InvalidArgumentException;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Composer\ComposerInterface;
use SilverStripe\Upgrader\Composer\SilverstripePackageInfo;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Utility class to help switch to using the public webroot. Only use by the `webroot` command.
 */
class WebRootMover
{

    /**
     * List of all official MD5 hashes for the root .htaccess file as determined by official releases of
     * silverstripe/installer.
     */
    const HTACCESS_HASHES = [
        "3.0.0" => "23cbaf9134451a28d7c1bf12039ca3d9",
        "3.0.4" => "a56ba9c80d6db529325488124d41fbec",
        "3.0.6" => "90f98824cc0774ab5411754f5bd7de18",
        "3.0.9" => "ab6ca534c7272670781700728aac9cf4",
        "3.1.7" => "aee423525d7ecdf03caa01ce2674662b",
        "3.1.9" => "7da44c41ce20e8de4b3c43d396e64a45",
        "3.1.10" => "8e4d6b0ceec631ed3915c9ccd309349b",
        "3.1.11" => "c50d17061b47277d48f7bbd40500c6d0",
        "3.1.13" => "836947940aec5289d6fc84665d5fca0b",
        "3.1.14" => "9b3abe2df028df42e0c63469e26f44af",
        "4.1.0" => "cbf4f41c9ec7a1b3b4b3c2f4fca668fc",
    ];

    /**
     * List of all official MD5 hashes for the root web.config file as determined by official releases of
     * silverstripe/installer.
     */
    const WEBCONFIG_HASHES = [
        "3.0.0" => "adad0226856abf247bf49db5c2daa1c2",
        "3.0.4" => "53a465e84d2f957f1a09181da44d7148",
        "3.0.6" => "411369990365352d2954a65b28166b9c",
        "3.1.11" => "b223f23ca26800ef97fa3964bd34ac8b",
        "3.1.14" => "d3ada35b3eb8532b3e1039a92292bc08"
    ];

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
     * Path to recipe-core in vendor folder
     */
    const RECIPE_CORE_PATH =
        'vendor' . DIRECTORY_SEPARATOR .
        'silverstripe' . DIRECTORY_SEPARATOR .
        'recipe-core' . DIRECTORY_SEPARATOR;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var DiskCollection
     */
    private $disk;

    /**
     * @var ComposerInterface
     */
    private $composer;

    public function __construct(string $rootPath, ComposerInterface $composer)
    {
        $this->rootPath = $rootPath;
        $this->disk = new DiskCollection($rootPath);
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
        // Make sure we have recipe-core install
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

        // Make sure we don't alrady have a recipe core folder.
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
     * Move server configuration file (.htaccess, web.config) around.
     * @internal Depends on `checkPrerequesites` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveServerConfigFile(CodeChangeSet &$diff)
    {
        $this->processLegacyServerConfigFile($diff, '.htaccess', self::HTACCESS_HASHES);
        $this->processLegacyServerConfigFile($diff, 'web.config', self::WEBCONFIG_HASHES);
    }

    /**
     * Process the provided legacy server config file. The file is expected to be in the root of the project.
     * If the file is unchanged from previous versions or does not exists, we just override remove it and copy the
     * recipe core version instead.
     *
     * If the file exists and has been changed, we move it to the public folder with a warning.
     *
     * @param CodeChangeSet $diff
     * @param string $filename
     * @param string[] $unchangedHashes List of valid MD5 hashes used to determine if the file has changed.
     * @return void
     */
    private function processLegacyServerConfigFile(
        CodeChangeSet &$diff,
        string $filename,
        array $unchangedHashes
    ): void {
        // We are tracking 2 metrics:
        // * state of the root server file ... absent, unchanged, changed (3 different scenarios);
        // * whatever recipe core provides a root file and a public file, or just a public file (2 different scenarios).
        // So there's 6 different combinations.


        // Get reference to the core files.
        $targetPath = self::PUBLIC . DIRECTORY_SEPARATOR . $filename;
        $coreRootFile = $this->getRecipeCoreFileContent($filename);
        $corePublicFile = $this->getRecipeCoreFileContent($targetPath);

        // Try to get the disk item
        $originFile = $this->disk->exists($filename) ? $this->disk->itemByPath($filename) : false;

        // Figuring out base conditions
        $originCondition = $originFile ?
            (in_array($originFile->getMd5Hash(true), $unchangedHashes) ?
                'unchanged':
                'changed'
            ) :
            'absent';
        $coreCondition = $coreRootFile ? 'root_file' : 'no_root_file';

        // Apply change based on conditions
        if ($originCondition == 'absent' && $coreCondition == 'no_root_file') {
            // Copy the core file to the public folder
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), false);
        } elseif ($originCondition == 'absent' && $coreCondition == 'root_file') {
            // Copy public and root core files to project
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), false);
            $diff->addFileChange($filename, $coreRootFile->getContents(), false);
        } elseif ($originCondition == 'unchanged' && $coreCondition == 'no_root_file') {
            // Move the server file from root to public and override with content of recipe-core public file
            $diff->addFileChange($filename, $corePublicFile->getContents(), $originFile->getContents(), $targetPath);
        } elseif ($originCondition == 'unchanged' && $coreCondition == 'root_file') {
            // Override root file with content of recipe-core root file
            $diff->addFileChange($filename, $coreRootFile->getContents(), $originFile->getContents());
            // Copy public recipe-core file to public.
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), false);
        } elseif ($originCondition == 'changed' && $coreCondition == 'no_root_file') {
            // Move root file to public without change.
            $diff->move($filename, $targetPath);
            // Add a warning
            $diff->addWarning($filename, 0, sprintf(
                '`%s` has been modified from the generic version provided by SilverStripe.'.
                'You\'ll need to compare it to `%s` and resolve any differences.',
                $filename,
                self::RECIPE_CORE_PATH . $targetPath
            ));
        } elseif ($originCondition == 'changed' && $coreCondition == 'root_file') {
            // Override root file with recipe-core root file.
            $diff->addFileChange($filename, $coreRootFile->getContents(), $originFile->getContents());
            // Copy content of root file to public as a new file.
            $diff->addFileChange($targetPath, $originFile->getContents(), false);
            // Add a warning
            $diff->addWarning($targetPath, 0, sprintf(
                '`%s` has been modified from the generic version provided by SilverStripe.'.
                'You\'ll need to compare it to `%s` and resolve any differences.',
                $filename,
                self::RECIPE_CORE_PATH . $targetPath
            ));
        } else {
            // This should never occur.
            throw new LogicException(<<<EOF
Could not move server file because of unexpected condition. This is probably a bug in the upgrader. Please log an issue
at https://github.com/silverstripe/silverstripe-upgrader/issues/new.
EOF
            );
        }
    }

    /**
     * Retrieve a file from the recipe-core package in the vendor folder.
     * @param string $path Path to the source file to copy relative to the recipe-core folder.
     * @return ItemInterface|false
     */
    private function getRecipeCoreFileContent(string $path)
    {
        $vendorPath = self::RECIPE_CORE_PATH . $path;
        return $this->disk->exists($vendorPath) ?
           $this->disk->itemByPath($vendorPath) :
           false;
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
