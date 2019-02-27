<?php

namespace SilverStripe\Upgrader\Composer;

use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use InvalidArgumentException;
use SilverStripe\Upgrader\Composer\Rules\DependencyUpgradeRule;
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
            $content = $this->encode($content);
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
        return isset($this->composerJson['require']) ? $this->composerJson['require']: [];
    }

    /**
     * Explicitly set the `require` key on this schema.
     * @param array $require
     * @return void
     */
    public function setRequire(array $require): void
    {
        $this->composerJson['require'] = $require;
        $this->writeSchema();
    }

    /**
     * Get the requirements as defined by the `require` key in the composer file.
     * @return array
     */
    public function getRequireDev(): array
    {
        return isset($this->composerJson['require-dev']) ? $this->composerJson['require-dev'] : [];
    }

    /**
     * Explicitly set the `require` key on this schema.
     * @param array $require
     * @return void
     */
    public function setRequireDev(array $require): void
    {
        $this->composerJson['require-dev'] = $require;
        $this->writeSchema();
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
        $devOriginal = $this->getRequireDev();
        $devDependencies = $this->getRequireDev();
        foreach ($rules as $rule) {
            $console && $console->title($rule->getActionTitle());
            if ($rule->applicability() == DependencyUpgradeRule::REGULAR_DEPENDENCY_RULE) {
                $dependencies = $rule->upgrade($dependencies, $devDependencies, $this->exec);
            } elseif ($rule->applicability() == DependencyUpgradeRule::DEV_DEPENDENCY_RULE) {
                $devDependencies = $rule->upgrade($dependencies, $devDependencies, $this->exec);
            }

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

        // Try to use the same order as the original file, so the diff looks more relevant.
        $sortedDependencies = [];
        foreach ($devOriginal as $key => $constraint) {
            if (isset($devDependencies[$key])) {
                $sortedDependencies[$key] = $devDependencies[$key];
                unset($devDependencies[$key]);
            }
        }
        $devDependencies = array_merge($sortedDependencies, $devDependencies);

        // Build our propose new output
        $jsonData = $this->composerJson;
        $jsonData['require'] = $dependencies;
        $jsonData['require-dev'] = $devDependencies;
        $upgradedContent = $this->encode($jsonData);

        // Finally get our diff
        $change = new CodeChangeSet();
        $oldContent = $this->getContents();
        if ($oldContent != $upgradedContent) {
            $change->addFileChange($this->getFullPath(), $upgradedContent, $oldContent);
        }
        return $change;
    }

    /**
     * Converts a Composer Schema from a PHP array to a JSOn string.
     * @param array $json
     * @return string
     */
    private function encode(array $json): string
    {
        // Recast some keys as object to avoid them being outputted as empty arrays in the JSON.
        $keys = ['require', 'require-dev', 'extra', 'config'];
        foreach ($keys as $key) {
            if (isset($json[$key])) {
                $json[$key] = (object)$json[$key];
            }
        }

        return json_encode(
            $json,
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Write the current working schema to the file.
     * @return void
     */
    private function writeSchema(): void
    {
        $this->setContents($this->encode($this->composerJson));
    }
}
