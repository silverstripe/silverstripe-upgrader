<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Represent a rule to upgrade a Composer file.
 */
interface DependencyUpgradeRule
{

    /**
     * Title to display when this rule is being applied. Should start with a verb in the _Present Continuous_ tense.
     * e.g.: _Retrofitting booster rockets in Composer file_
     * @return string
     */
    public function getActionTitle(): string;

    /**
     * Apply this rule to the provided depednecy set.
     * @param  array        $dependencies
     * @param  ComposerExec $composer
     * @return array Updated dependencies
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array;

    /**
     * Return a list of warnings that got produced following a call to `upgrade`.
     * @return string[]
     */
    public function getWarnings(): array;
}
