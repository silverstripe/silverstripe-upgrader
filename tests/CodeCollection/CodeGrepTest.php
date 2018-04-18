<?php

namespace SilverStripe\Upgrader\Tests\CodeCollection;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeGrep;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;

class CodeGrepTest extends TestCase
{
    const PATTERN = '/foobar/';

    public function testFindAsWarning()
    {
        $grep = new CodeGrep(
            self::PATTERN,
            new DiskCollection(__DIR__ . '/fixtures/GrepCode')
        );

        $codeChange = $grep->findAsWarning();
        $this->assertFalse($codeChange->hasWarnings('no_results.txt'), 'no_results.txt should not have warnings');
        $this->assertTrue($codeChange->hasWarnings('one_results.txt'), 'one_results.txt should have warnings');
        $this->assertTrue($codeChange->hasWarnings('many_results.txt'), 'many_results.txt should have warnings');
    }
}
