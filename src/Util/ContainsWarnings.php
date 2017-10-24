<?php

namespace SilverStripe\Upgrader\Util;

interface ContainsWarnings
{

    /**
     * @return Warning[]
     */
    public function getWarnings();
}
