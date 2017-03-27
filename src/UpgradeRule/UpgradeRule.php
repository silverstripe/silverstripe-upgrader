<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Abstract interface for a rule which can process a given file
 */
interface UpgradeRule
{
    /**
     * Called on a code collection prior to upgrade
     *
     * @param CollectionInterface $code
     * @param \SilverStripe\Upgrader\CodeCollection\CodeChangeSet $changeset
     */
    public function beforeUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset);

    /**
     * Called on a code collection after upgrade
     *
     * @param CollectionInterface $code
     * @param \SilverStripe\Upgrader\CodeCollection\CodeChangeSet $changeset
     */
    public function afterUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset);

    /**
     * Get name for this rule
     *
     * @return string
     */
    public function getName();

    /**
     * Upgrades the contents of the given file
     * Returns string containing the new code.
     *
     * @param string $contents
     * @param ItemInterface $file
     * @param CodeChangeSet $changeset Changeset to add warnings to
     * @return string
     */
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset);

    /**
     * Apply the parameters to this object and return $this, for fluent call-style
     *
     * @param array $parameters
     * @return $this
     */
    public function withParameters(array $parameters);

    /**
     * Returns true if this upgrad rule applies to the given file
     * Checks fileExtensions parameters
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo(ItemInterface $file);
}
