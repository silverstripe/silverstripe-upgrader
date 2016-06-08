<?php

namespace SilverStripe\Upgrader;

use SilverStripe\Upgrader\Upgrader\RenameClass;

class UpgradeSpec
{

    private $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
        /*
        // Temporary stub while we test other things
        yield (new RenameClasses())->withProperties([
            'mappings' => [
                'DataObject' => 'SilverStripe\Model\DataObject',
            ],
        ]);
        */
    }

    /**
     * Returns an iterator that yields each upgrader to uapp
     */
    public function rules()
    {
        return $this->rules;
    }
}
