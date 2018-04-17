<?php

namespace SilverStripe\Upgrader\Util;

use LogicException;

/**
 * Convert the old `mysite` and `mysite/code` structure to an `app` and `app/src` structure.
 *
 * @internal The various folder names can be customised. Don't expect we'll be using this feature at first. It's just
 * there the future proofing the logic.
 */
class ProjectReorganiser
{

    /**
     * There's no legacy structure and there's no updated structure.
     * @var int
     */
    const NOTHING = 0x00;

    /**
     * A Legacy structure is present without the updated structure. The legacy structure can be upgraded
     * @var int
     */
    const UPGRADABLE_LEGACY = 0x01;

    /**
     * A Legacy structure is present however renaming it to the updated structure would override an existing folder.
     * @var int
     */
    const BLOCKED_LEGACY = 0x11;

    /**
     * No Legacy structure is present and the updated structure is already in use. Nothing to do.
     * @var int
     */
    const ALREADY_UPGRADED = 0x10;

    /**
     * Root Path of the SS3 site.
     * @var string
     */
    private $rootPath;

    /**
     * Name of the legacy project folder.
     * @var string
     */
    private $legacyProject;

    /**
     * New Project folder name.
     * @var string
     */
    private $updatedProject;

    /**
     * Name of the legacy code folder
     * @var string
     */
    private $legacyCode;

    /**
     * Name of the new code folder.
     * @var string
     */
    private $updatedCode;

    /**
     * Instanciate an instance of `ProjectReorganiser`.
     * @param string $rootPath       Root path of the project to reorganize
     * @param string $legacyProject  Defaults to `mysite`
     * @param string $updatedProject Defaults to `app`
     * @param string $legacyCode     Defaults to `code`
     * @param string $updatedCode    Defaults to `src`
     */
    public function __construct(
        string $rootPath,
        string $legacyProject = 'mysite',
        string $updatedProject = 'app',
        string $legacyCode = 'code',
        string $updatedCode = 'src'
    ) {
        $this->rootPath = $rootPath;
        $this->legacyProject = $legacyProject;
        $this->updatedProject = $updatedProject;
        $this->legacyCode = $legacyCode;
        $this->updatedCode = $updatedCode;
    }

    /**
     * Check if there's a legacy project folder and if it can be moved.
     * @return int
     */
    public function checkProjectFolder()
    {
        return $this->checkGenericFolder($this->legacyProject, $this->updatedProject);
    }

    /**
     * Check if there's a legacy code folder and if it can be moved.
     * @return int
     */
    public function checkCodeFolder()
    {
        $projectStatus = $this->checkProjectFolder();
        if (in_array($projectStatus, [self::NOTHING, self::BLOCKED_LEGACY])) {
            // If we have both `app` and `mysite` ... or neither, let's bail.
            return $projectStatus;
        }

        // Pick whatever we'll be looking for our code folder in `myste` or `app`.
        $projectFolder = $projectStatus == self::ALREADY_UPGRADED ?
            $this->updatedProject :
            $this->legacyProject;

        return $this->checkGenericFolder(
            $projectFolder . DIRECTORY_SEPARATOR . $this->legacyCode,
            $projectFolder . DIRECTORY_SEPARATOR . $this->updatedCode
        );

    }

    /**
     * Generic method for checking whatever a legacy and updated path are in use.
     * @param  string $legacyPath
     * @param  string $updatedPath
     * @return int
     */
    private function checkGenericFolder($legacyPath, $updatedPath)
    {
        // Do we have a mysite?
        $legacy = $this->folderExists($legacyPath) ?
            self::UPGRADABLE_LEGACY :
            self::NOTHING;

        // Do we have an app folder
        $updated = $this->folderExists($updatedPath) ?
            self::ALREADY_UPGRADED :
            self::NOTHING;

        // Combine both possiblity.
        return $legacy | $updated;
    }


    /**
     * Try to rename legacy project folder.
     * @throws LogicException
     */
    public function moveProjectFolder($dryrun = false): array
    {
        switch ($this->checkCodeFolder()) {
            case self::BLOCKED_LEGACY:
                throw new LogicException('Target folder already exists.');

            case self::UPGRADABLE_LEGACY:
                return $this->moveGenericFolder(
                    $this->legacyProject,
                    $this->updatedProject,
                    $dryrun
                );

            case self::ALREADY_UPGRADED:
            case self::NOTHING:
                return [];
        }
    }

    /**
     * Try to rename a legacy `code` folder to `src`.
     * @throws LogicException
     */
    public function moveCodeFolder($dryrun = false): array
    {
        switch ($this->checkCodeFolder()) {
            case self::BLOCKED_LEGACY:
                throw new LogicException('Target folder already exists.');

            case self::UPGRADABLE_LEGACY:
                // Pick if we are using `app` or `mysite`
                $projectStatus = $this->checkProjectFolder();
                $projectFolder = $projectStatus == self::ALREADY_UPGRADED ?
                    $this->updatedProject :
                    $this->legacyProject;

                return $this->moveGenericFolder(
                    $projectFolder . DIRECTORY_SEPARATOR . $this->legacyCode,
                    $projectFolder . DIRECTORY_SEPARATOR . $this->updatedCode,
                    $dryrun
                );

            case self::ALREADY_UPGRADED:
            case self::NOTHING:
                return [];
        }
    }

    /**
     * Utility method for moving a folder around and return an array describing the move.
     * @param  string $legacyPath
     * @param  string $updatedPath
     * @param  bool   $dryrun Whatever we are doing this for real
     * @return array
     */
    private function moveGenericFolder($legacyPath, $updatedPath, $dryrun): array
    {
        $orig = $this->makeAbsolute($legacyPath);
        $dest = $this->makeAbsolute($updatedPath);

        if (!$dryrun) {
            rename($orig, $dest);
        }

        return [$orig => $dest];

    }

    /**
     * Utility method to check if the given path exists and if it's a folder.
     * @param  string  $relativePath
     * @return boolean
     */
    private function folderExists(string $relativePath)
    {
        $path = $this->makeAbsolute($relativePath);
        return file_exists($path) && is_dir($path);
    }


    /**
     * Take a relative path and make it absolute.
     * @param  string $relativePath
     * @return string
     */
    private function makeAbsolute($relativePath)
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . $relativePath;
    }
}
