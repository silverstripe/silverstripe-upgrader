<?php

namespace SilverStripe\Upgrader\Composer;

use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Represent a `composer.json` file and provide methods to interact with it. Note that this object doesn't
 * automatically detect changes to the composer file. So you may need to call the `parse` method after performing an
 * operation on this file to get the latest values.
 */
class ComposerFile extends DiskItem
{

    use TemporaryFile;

    /**
     * Parsed content of a composer.json file
     * @var array
     */
    protected $composerJson = [
        "name" => "",
        "description" => "",
        "license" => "",
        "authors" => [ ],
        "require" => [ ],
        "require-dev" => [ ],
        "minimum-stability" => "dev",
        "prefer-stable" => true
    ];

    /**
     * @var ComposerExec
     */
    protected $exec;

    /**
     * Flag to indicate this is a temporary composer file that doesn't need to be retained after execution.
     * @var bool
     */
    protected $temporary;

    /**
     * Instantiate a new ComposerFile
     * @param ComposerExec $exec      Composer executable to use when running operation on this file.
     * @param string       $basePath  Directory containing this composer file.
     * @param boolean      $temporary Whatever this schema should be retain after execution.
     */
    public function __construct(ComposerExec $exec, string $basePath, bool $temporary = false)
    {
        parent::__construct($basePath, 'composer.json');
        $this->exec = $exec;
        $this->temporary = $temporary;
        $this->parse();
    }

    /**
     * Self-destruct the project if it's meant to be temporary.
     */
    public function __destruct()
    {
        if ($this->temporary) {
            $fs = new Filesystem();
            $fs->remove($this->getBasePath());
        }
    }

    /**
     * Try to parse the composer file content.
     * @throws RuntimeException If the file is invalid.
     * @return void
     */
    public function parse()
    {
        if ($this->exec->validate($this->getFullPath(), true)) {
            $this->composerJson = json_decode($this->getContents(), true);
        }
    }

    /**
     * Validate a composer file.
     * @param  array|string|null $content Schema content either.
     * @throws InvalidArgumentException If `$content` is of an invalid type.
     * @return boolean
     */
    public function validate($content = null)
    {
        // If we haven't been provided any content, assume we are validating the current file.
        if ($content === null) {
            $content = $this->composerJson;
        }

        // If we have an array, JSON code it
        if (is_array($content)) {
            // This is a bit of silly hack. If require is empty it will be outputted. as an array. `composer validate`
            // expect it to be an object, so we just had a dummy key pair in there to make sure it always generated as
            // an object.
            if (isset($content['require']) && empty($content['require'])) {
                $content['require']['php'] = '*';
            }
            $content = json_encode($content);
        }

        if (!is_string($content)) {
            throw new InvalidArgumentException('$content must be of type string, array or null');
        }

        $this->writeTmpFile($content);
        $path = $this->getTmpFilePath();

        return $this->exec->validate($path);
    }

    /**
     * Get the requirements as defined by the `require` key in the composer file.
     * @return array
     */
    public function getRequire(): array
    {
        return $this->composerJson['require'];
    }


    /**
     * Apply the following upgrade rules to the composer file and get the difference
     * @param  Rules\DependencyUpgradeRule[] $rules List of rules to apply to the the dependencies.
     * @param  SymfonyStyle $console Optional. Can be use to log additional info as the upgrade progress.
     * @return CodeChangeSet
     */
    public function upgrade(array $rules, SymfonyStyle $console = null): CodeChangeSet
    {
        // Apply the change
        $original = $this->getRequire();
        $dependencies = $this->getRequire();
        foreach ($rules as $rule) {
            $console && $console->title($rule->getActionTitle());
            $dependencies = $rule->upgrade($dependencies, $this->exec);

            $warnings = $rule->getWarnings();
            if (empty($warnings)) {
                $console && $console->text("Done.");
            } else {
                $console && $console->caution("Done with warnings.");
                $console && $console->listing($warnings);
            }
        }

        // Try to use the same order as the original file, so the diff looks more relevant.
        $sortedDependencies = [];
        foreach ($original as $key => $constraint) {
            if (isset($dependencies[$key])) {
                $sortedDependencies[$key] = $dependencies[$key];
                unset($dependencies[$key]);
            }
        }
        $dependencies = array_merge($sortedDependencies, $dependencies);

        // Build our propose new output
        $jsonData = $this->composerJson;
        $jsonData['require'] = $dependencies;
        $upgradedContent = json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Finally get our diff
        $change = new CodeChangeSet();
        $oldContent = $this->getContents();
        if ($oldContent != $upgradedContent) {
            $change->addFileChange($this->getFullPath(), $upgradedContent, $oldContent);
        }
        return $change;
    }
}
