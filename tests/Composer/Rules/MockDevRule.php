<?php

namespace SilverStripe\Upgrader\Tests\Composer\Rules;

use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\Rules\DependencyUpgradeRule;

class MockDevRule implements DependencyUpgradeRule
{

    public function getActionTitle(): string
    {
        return 'Mock rule';
    }

    public function upgrade(array $dependencies, array $devDependencies, ComposerExec $composer): array
    {
        $devDependencies['silverstripe-upgrader/mock-dev-rule'] = '~1.2.3';
        return $devDependencies;
    }

    public function getWarnings(): array {
        return ['mock warning'];
    }

    /**
     * Define to what kind of dependency the rule should apply. Possible values are:
     * * DependencyUpgradeRule::DEV_DEPENDENCY_RULE
     * * DependencyUpgradeRule::REGULAR_DEPENDENCY_RULE
     * @return int
     */
    public function applicability(): int
    {
        return DependencyUpgradeRule::DEV_DEPENDENCY_RULE;
    }

}