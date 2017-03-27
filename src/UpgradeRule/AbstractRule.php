<?php


namespace SilverStripe\Upgrader\UpgradeRule;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;

/**
 * Base upgrade rule class
 */
abstract class AbstractRule implements UpgradeRule
{
    protected $parameters = [];

    /**
     * Called on a code collection prior to upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function beforeUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset)
    {
    }

    /**
     * Called on a code collection after upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function afterUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset)
    {
    }

    /**
     * Get name for this rule
     *
     * @return string
     */
    public function getName()
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getShortName();
    }

    /**
     * Apply the parameters to this object and return $this, for fluent call-style
     *
     * @param array $parameters
     * @return $this
     */
    public function withParameters(array $parameters)
    {
        // Merge with default config
        $this->parameters = array_merge(
            [
                'fileExtensions' => [],
                'mappings' => [],
                'skipConfigs' => [],
                'skipYML' => [],
            ],
            $parameters
        );
        return $this;
    }
}
