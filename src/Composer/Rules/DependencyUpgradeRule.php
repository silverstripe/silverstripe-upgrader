<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Represent a rule to upgrade a Composer file.
 */
interface DependencyUpgradeRule
{

    /**
     * This rule should apply to dev dependencies.
     */
    const DEV_DEPENDENCY_RULE = 0b01;

    /**
     * This rule should apply to regular dependencies.
     */
    const REGULAR_DEPENDENCY_RULE = 0b10;

    /**
     * Title to display when this rule is being applied. Should start with a verb in the _Present Continuous_ tense.
     * e.g.: _Retrofitting booster rockets in Composer file_
     * @return string
     */
    public function getActionTitle(): string;

    /**
     * Apply this rule to the provided dependency set.
     * @param  array        $dependencies
     * @param  array        $devDependencies
     * @param  ComposerExec $composer
     * @return array Updated dependencies
     */
    public function upgrade(array $dependencies, array $devDependencies, ComposerExec $composer): array;

    /**
     * Return a list of warnings that got produced following a call to `upgrade`.
     * @return string[]
     */
    public function getWarnings(): array;

    /**
     * Define to what kind of dependency the rule should apply. Possible values are:
     * * DependencyUpgradeRule::DEV_DEPENDENCY_RULE
     * * DependencyUpgradeRule::REGULAR_DEPENDENCY_RULE
     * @return integer
     */
    public function applicability(): int;
}
