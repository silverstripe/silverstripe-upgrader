<?php

namespace SilverStripe\Upgrader\UpgradeRule\YML;

use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractRule;

abstract class YMLUpgradeRule extends AbstractRule
{
    /**
     * Returns true if this upgrad rule applies to the given file
     * Checks fileExtensions parameters
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo(ItemInterface $file)
    {
        return preg_match('#\.y(a?)ml$#', $file->getFullPath());
    }


    /**
     * Recursively upgrades content
     *
     * @param array $data
     * @return array Upgraded array
     */
    protected function upgradeArray($data)
    {
        $upgraded = [];
        foreach ($data as $key => $value) {
            // Upgrade key
            if (is_string($key)) {
                $key = $this->upgradeString($key);
            }

            // Upgrade, or recurse value
            if (is_array($value)) {
                $value = $this->upgradeArray($value);
            } elseif (is_string($value)) {
                $value = $this->upgradeString($value);
            }
            $upgraded[$key] = $value;
        }
        return $upgraded;
    }

    /**
     * Upgrade a string value
     *
     * @param string $value
     * @return string
     */
    protected function upgradeString($value)
    {
        // Skip un-upgradable strings
        if (is_numeric($value) || !is_string($value)) {
            return $value;
        }

        // Check prefix
        $prefix = '';
        if (strpos($value, '%$') === 0) {
            $value = substr($value, 2);
            $prefix = '%$';
        }

        // Upgrade if in mapping, and not exclusions
        if (isset($this->parameters['mappings'][$value]) && !in_array($value, $this->parameters['skipYML'])) {
            $value = $this->parameters['mappings'][$value];
        }

        // Upgrade
        return $prefix . $value;
    }
}
