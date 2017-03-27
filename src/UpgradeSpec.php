<?php

namespace SilverStripe\Upgrader;

use SilverStripe\Upgrader\UpgradeRule\UpgradeRule;

class UpgradeSpec
{
    /**
     * @var UpgradeRule[]
     */
    private $rules;

    /**
     * Create upgrade spec with given rules
     *
     * @param UpgradeRule[] $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Add a new rule
     *
     * @param UpgradeRule $rule
     * @return $this
     */
    public function addRule(UpgradeRule $rule)
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Returns an iterator that yields each upgrader to uapp
     */
    public function rules()
    {
        return $this->rules;
    }
}
