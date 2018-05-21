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
        "core-1.0.0" => "08964c3c62d56e0f8ef0c597312e60c2",
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
        "3.1.14" => "d3ada35b3eb8532b3e1039a92292bc08",
        "core-1.0.0" => "b3422ef2648272f606c29b3fc17b5be3"
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
     * Root server file is absent from project. (Internal only)
     */
    const SERVER_FILE_ABSENT = 0x00;

    /**
     * Project root server file is unchanged. (Internal only)
     */
    const SERVER_FILE_UNCHANGED = 0x01;

    /**
     * Project root serer file is altered from the official release. (Internal only)
     */
    const SERVER_FILE_CHANGED = 0x02;

    /**
     * Recipe Core only provides a server file for the public folder. (Internal only)
     */
    const CORE_SERVER_FILE_PUBLIC_ONLY = 0x00;

    /**
     * Recipe Core provides a server file for the root and public folders. (Internal only)
     */
    const CORE_SERVER_FILE_ROOT_AND_PUBLIC = 0x10;


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
     * Execute all the steps to set up the public folder.
     * @throws InvalidArgumentException
     * @return CodeChangeSet
     */
    public function move(): CodeChangeSet
    {
        $this->checkPrerequisites();
        $diff = new CodeChangeSet();

        $this->moveServerConfigFile($diff);
        $this->moveAssets($diff);
        $this->moveInstallerFiles($diff);

        return $diff;
    }


    /**
     * Make sure the current project meet the prerequisites to be migrated to the public webroot:
     * * Using recipeCore ^1.1
     * * Does not have a public folder or the public folder is empty.
     *
     * @throws InvalidArgumentException
     */
    public function checkPrerequisites(): void
    {
        $this->checkRecipeCorePrerequisite();
        $this->checkPublicFolderPrerequisite();
    }

    /**
     * Make sure we have the minimal version of recipe-core installed.
     *
     * @throws InvalidArgumentException If we do not have the minimal version recipe-core.
     * @return void
     */
    private function checkRecipeCorePrerequisite(): void
    {
        // Get package info for the project
        $packageInfo = $this->composer->show();
        $packageInfo = array_values(array_filter($packageInfo, function ($package) {
            return $package['name'] == SilverstripePackageInfo::RECIPE_CORE;
        }));

        // Make sure we have Recipe core installed
        if (empty($packageInfo)) {
            throw new InvalidArgumentException(sprintf(
                'To use the public webroot, your project must be using %s 1.1 or higher. ' .
                'It is not currently installed.',
                SilverstripePackageInfo::RECIPE_CORE
            ));
        }

        /**
         * @var string
         */
        $version = $packageInfo[0]['version'];

        // Strip the commit hash from the version if present
        if (preg_match('/(.+) [a-f0-9]/', $version, $matches)) {
            $version = $matches[1];
        }

        // Make sure our version of recipe core meet the constrain requirements.
        if (!Semver::satisfies($version, self::CORE_CONSTRAINT)) {
            throw new InvalidArgumentException(sprintf(
                'To use the public webroot, your project must be using %s 1.1 or higher. ' .
                'Version %s is currently installed.',
                SilverstripePackageInfo::RECIPE_CORE,
                $packageInfo[0]['version']
            ));
        }
    }

    /**
     * Make sure the we do not have a public folder or that it's empty.
     * @throw InvalidArgumentException There's a non-empty public folder in the project.
     * @return void
     */
    private function checkPublicFolderPrerequisite(): void
    {
        // Make sure we don't already have a public.
        $publicPath = $this->publicPath();
        if (file_exists($publicPath) &&
            (
                !is_dir($publicPath) ||
                count(scandir($publicPath)) > 2
            )
        ) {
            throw new InvalidArgumentException(
                'There\'s already a non empty `public` folder in your project root. ' .
                'You might already be using the public web root.'
            );
        }
    }

    /**
     * Move server configuration file (.htaccess, web.config) around.
     * @internal Depends on `checkPrerequisites` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveServerConfigFile(CodeChangeSet $diff)
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
        CodeChangeSet $diff,
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
                self::SERVER_FILE_UNCHANGED:
                self::SERVER_FILE_CHANGED
            ) :
            self::SERVER_FILE_ABSENT;
        $coreCondition = $coreRootFile ?
            self::CORE_SERVER_FILE_ROOT_AND_PUBLIC :
            self::CORE_SERVER_FILE_PUBLIC_ONLY;
        $condition = $originCondition | $coreCondition;

        // Apply change based on condition
        if ($condition == (self::SERVER_FILE_ABSENT | self::CORE_SERVER_FILE_PUBLIC_ONLY)) {
            // Copy the core file to the public folder
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), null);
        } elseif ($condition ==  (self::SERVER_FILE_ABSENT | self::CORE_SERVER_FILE_ROOT_AND_PUBLIC)) {
            // Copy public and root core files to project
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), null);
            $diff->addFileChange($filename, $coreRootFile->getContents(), null);
        } elseif ($condition == (self::SERVER_FILE_UNCHANGED | self::CORE_SERVER_FILE_PUBLIC_ONLY)) {
            // Move the server file from root to public and override with content of recipe-core public file
            $diff->addFileChange($filename, $corePublicFile->getContents(), $originFile->getContents(), $targetPath);
        } elseif ($condition == (self::SERVER_FILE_UNCHANGED | self::CORE_SERVER_FILE_ROOT_AND_PUBLIC)) {
            // Override root file with content of recipe-core root file
            $diff->addFileChange($filename, $coreRootFile->getContents(), $originFile->getContents());
            // Copy public recipe-core file to public.
            $diff->addFileChange($targetPath, $corePublicFile->getContents(), null);
        } elseif ($condition == (self::SERVER_FILE_CHANGED | self::CORE_SERVER_FILE_PUBLIC_ONLY)) {
            // Move root file to public without change.
            $diff->move($filename, $targetPath);
            // Add a warning
            $diff->addWarning($filename, 0, sprintf(
                '`%s` has been modified from the generic version provided by SilverStripe.'.
                'You\'ll need to compare it to `%s` and resolve any differences.',
                $filename,
                self::RECIPE_CORE_PATH . $targetPath
            ));
        } elseif ($condition == (self::SERVER_FILE_CHANGED | self::CORE_SERVER_FILE_ROOT_AND_PUBLIC)) {
            // Override root file with recipe-core root file.
            $diff->addFileChange($filename, $coreRootFile->getContents(), $originFile->getContents());
            // Copy content of root file to public as a new file.
            $diff->addFileChange($targetPath, $originFile->getContents(), null);
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
at https://github.com/silverstripe/silverstripe-upgrader/issues/new and include the following information: 
`\$condition=$condition`.
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
    public function moveAssets(CodeChangeSet $diff): void
    {
        if ($this->disk->exists('assets')) {
            $diff->move('assets', 'public/assets');
        }
    }

    /**
     * Move files that would normally have been provided by the installer.
     * @internal Depends on `initialisePublicFolder` having been run.
     * @param CodeChangeSet $diff
     */
    public function moveInstallerFiles(CodeChangeSet $diff)
    {
        // Move some common non-essnetial file from recipe-core to public.
        foreach (['index.php', '.gitignore'] as $filename) {
            $targetPath = self::PUBLIC . DIRECTORY_SEPARATOR . $filename;
            $recipeCoreFile = $this->getRecipeCoreFileContent($targetPath);
            if ($recipeCoreFile) {
                $diff->addFileChange($targetPath, $recipeCoreFile->getContents(), null);
            }
        }

        // Move some common non-essential file from root to public.
        foreach (['favicon.ico'] as $filename) {
            if ($this->disk->exists($filename)) {
                $diff->move($filename, self::PUBLIC . DIRECTORY_SEPARATOR . $filename);
            }
        }

        // Delete some unneeded file from the root.
        foreach (['index.php'] as $filename) {
            if ($this->disk->exists($filename)) {
                $diff->remove($filename);
            }
        }
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
