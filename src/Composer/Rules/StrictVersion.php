<?php

namespace SilverStripe\Upgrader\Composer\Rules;

use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Rule to update carrets constraints to the stricter tilde constraint.
 */
class StrictVersion implements DependencyUpgradeRule
{

    private $warnings = ['`upgrade` was not called.'];

    /**
     * @inheritdoc
     * @return string
     */
    public function getActionTitle(): string
    {
        return 'Using strict version constraint';
    }


    /**
     * @inheritDoc
     * @param  array $dependencies Dependencies to upgrade.
     * @param  ComposerExec $composer Composer executable.
     * @return array Upgraded dependencies.
     */
    public function upgrade(array $dependencies, ComposerExec $composer): array
    {
        $this->warnings = [];
        $regex = '/^\^(\d+\.\d+)(\.[0-9a-z]+)?/';

        foreach ($dependencies as &$constraint) {
            // Loop through each constraint and try to find the one that start with a caret.
            $constraint = preg_replace_callback($regex, function ($matches) {

                // Find out if we have missing digit at the end
                $finalDigit = $matches[2] ?? '.0';

                // If our missing digit is an actual digit
                if (preg_match('/^\.\d+$/', $finalDigit)) {
                    return '~' . $matches[1] . ($matches[2] ?? '.0');
                } else {
                    // Handle weird edge case where the last digit is not a digit. e.g.: ^1.2.x-dev
                    return $matches[0];
                }
            }, $constraint);
        }

        return $dependencies;
    }

    /**
     * @inheritdoc
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
