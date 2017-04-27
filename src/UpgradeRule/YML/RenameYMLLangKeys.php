<?php

namespace SilverStripe\Upgrader\UpgradeRule\YML;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Renames YML locale keys
 */
class RenameYMLLangKeys extends YMLUpgradeRule
{
    /**
     * Upgrades the contents of the given file
     * Returns string containing the new code.
     *
     * @param string $contents
     * @param ItemInterface $file
     * @param \SilverStripe\Upgrader\CodeCollection\CodeChangeSet $changeset Changeset to add warnings to
     * @return string
     */
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        // Parse file
        $data = Yaml::parse($contents);

        // Migrate all data keys
        $upgradedData = [];
        $changed = false;
        foreach ($data as $locale => $messages) {
            if (empty($messages)) {
                continue;
            }
            $upgradedData[$locale] = [];
            foreach ($messages as $key => $value) {
                $newKey = $this->upgradeString($key);
                if ($newKey !== $key) {
                    $changed = true;
                }
                // Safely merge
                if (isset($upgradedData[$locale][$newKey])) {
                    $upgradedData[$locale][$newKey] = array_merge(
                        $upgradedData[$locale][$newKey],
                        $value
                    );
                } else {
                    $upgradedData[$locale][$newKey] = $value;
                }
                ksort($upgradedData[$locale][$newKey]);
            }
            ksort($upgradedData[$locale]);
        }

        if (!$changed) {
            return $contents;
        }

        // dump changed content
        return Yaml::dump($upgradedData, 9999, 2, true, false);
    }

    public function appliesTo(ItemInterface $file)
    {
        // Only lang/*.yml files
        // e.g. `lang/sr_RS@latin.yml`
        return preg_match('#/lang/[-_\w@]+\.y(a?)ml$#', $file->getFullPath());
    }
}
