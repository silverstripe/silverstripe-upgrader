<?php

namespace SilverStripe\Upgrader\UpgradeRule\JS;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractRule;

class RenameJSLangKeys extends AbstractRule
{
    /**
     * Upgrades the contents of the given file
     * Returns string containing the new code.
     *
     * @param string $contents
     * @param ItemInterface $file
     * @param CodeChangeSet $changeset Changeset to add warnings to
     * @return string
     */
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        // Ensure file is parsable
        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $changeset->addWarning($file->getPath(), 0, json_last_error_msg());
            return $contents;
        }

        // Migrate all data keys
        $upgradedData = [];
        $changed = false;
        foreach ($data as $key => $value) {
            $newKey = $this->upgradeString($key);
            if ($newKey !== $key) {
                $changed = true;
            }
            $upgradedData[$newKey] = $value;
        }
        if (!$changed) {
            return $contents;
        }

        // Sort and dump, but only re-sort and format if changed
        ksort($upgradedData);
        return json_encode($upgradedData, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Upgrade a string value
     *
     * @param string $value
     * @return string
     */
    protected function upgradeString($value)
    {
        // Check if class.key format
        $splitPos = strpos($value, '.');
        if (!$splitPos) {
            return $value;
        }

        // Split into parts
        $class = substr($value, 0, $splitPos); // 'AssetAdmin'
        $rest = substr($value, $splitPos); // '.NAME'

        // Upgrade if in mapping, and not exclusions
        if (isset($this->parameters['mappings'][$class])) {
            $class = $this->parameters['mappings'][$class];
        }

        // Upgrade
        return $class . $rest;
    }

    /**
     * Returns true if this upgrad rule applies to the given file
     * Checks fileExtensions parameters
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo(ItemInterface $file)
    {
        // Only upgrade lang/src/*.js / .json files
        // Let dist files be built on release via cow
        return preg_match('#/lang/src/[-_\w@]+\\.js(on)?$#', $file->getFullPath());
    }
}
