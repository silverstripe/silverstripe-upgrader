<?php

namespace SilverStripe\Upgrader\UpgradeRule\SS;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Renames template locale keys
 */
class RenameTemplateLangKeys extends TemplateUpgradeRule
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

        $patterns = [
            '/_t\s*\(\s*(?:\'|")([A-Za-z_]+)\./',
            '/<%t\s*(?:\'|")?([A-Za-z_]+)\./'
        ];
        $searches = [];
        $replacements = [];
        $mappings = $this->parameters['mappings'];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);
            if ($matches) {
                foreach ($matches as $m) {
                    list($tag, $key) = $m;
                    if (!isset($mappings[$key])) {
                        continue;
                    }

                    $newKey = str_replace('\\', '\\\\\\\\', $mappings[$key]);
                    $newTag = preg_replace('/[A-Za-z_]+\./', $newKey . '.', $tag);
                    $searches[] = $tag;
                    $replacements[] = $newTag;
                }
            }
        }

        return str_replace($searches, $replacements, $contents);
    }
}
