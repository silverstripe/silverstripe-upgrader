<?php

namespace SilverStripe\Upgrader\UpgradeRule\YML;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Renames classes in config files
 */
class UpdateConfigClasses extends YMLUpgradeRule
{
    const INJECTOR = 'SilverStripe\\Core\\Injector\\Injector';

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

        // Split config into multiple files
        $documents = $this->splitYamlDocuments($contents);
        $anyChanged = false;
        foreach ($documents as $key => $document) {
            list($newContent, $changed) = $this->upgradeBlock($document['content']);
            if ($changed) {
                $anyChanged = true;
                $documents[$key]['content'] = $newContent;
            }
        }

        if (!$anyChanged) {
            return $contents;
        }

        // Combine
        return $this->combineYamlDocuments($documents);
    }

    /**
     * Upgrade block of content.
     * Note: If there are any changes, all comments are stripped.
     * If there are no changes comments are retained.
     *
     * @param string $content
     * @return array Array with two items, upgraded content, and a changed flag
     */
    protected function upgradeBlock($content)
    {
        $data = Yaml::parse($content);
        $upgradedData = $this->upgradeArray($data);
        // Skip unaltered content
        if ($upgradedData === $data) {
            return [ $content, false ];
        }

        // Simplify redundant config blocks
        if (isset($upgradedData[self::INJECTOR])) {
            foreach ($upgradedData[self::INJECTOR] as $key => $value) {
                if (is_array($value) && count($value) === 1 && $value === ['class' => $key]) {
                    // MyClass: { class: MyClass } is redundant
                    unset($upgradedData[self::INJECTOR][$key]);
                } elseif ($key === $value) {
                    // MyClass: MyClass is redundant
                    unset($upgradedData[self::INJECTOR][$key]);
                }
            }
        }

        // dump changed content
        $content = Yaml::dump($upgradedData, 9999, 2, true, false);
        return [$content, true];
    }

    public function appliesTo(ItemInterface $file)
    {
        // Only _config/*.yml files
        return preg_match('#[/\\\\]_config[/\\\\][^/\\\\]+\.y(a?)ml$#', $file->getFullPath());
    }

    /**
     * Because multiple documents aren't supported in symfony/yaml, we have to manually
     * split the files up into their own documents before running them through the parser.
     * Note: This is not a complete implementation of multi-document YAML parsing. There
     * are valid yaml cases where this will fail, however they don't match our use-case.
     *
     * @param string $contents
     * @return array
     */
    protected function splitYamlDocuments($contents)
    {
        // Documents in this file
        $documents = [];
        $key = 0;

        // We need to loop through each file and parse the yaml content
        $lines = preg_split('~\R~u', $contents);

        $firstLine = true;
        $context = 'content';
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            if (!isset($documents[$key])) {
                $documents[$key] = [
                    'header' => '',
                    'content' => '',
                ];
            }

            if (($context === 'content' || $firstLine) && ($line === '---' || $line === '...')) {
                // '...' is the end of a document with no more documents after it.
                if ($line === '...') {
                    break;
                }

                $context = 'header';

                // If this isn't the first line (and therefor first doc) we'll increase
                // our document key
                if (!$firstLine) {
                    ++$key;
                }
            } elseif ($context === 'header' && $line === '---') {
                $context = 'content';
            } else {
                $documents[$key][$context] .= $line.PHP_EOL;
            }

            $firstLine = false;
        }

        return $documents;
    }

    /**
     * Merge all yml documents into a single string
     *
     * @param array $documents
     * @return string
     */
    protected function combineYamlDocuments($documents)
    {
        $result = '';
        foreach ($documents as $document) {
            if ($document['header']) {
                $result .= '---'.PHP_EOL.$document['header'].'---'.PHP_EOL;
            }
            $result .= $document['content'];
        }
        return $result;
    }
}
