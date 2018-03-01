<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameTranslateKeys;

class RenameTranslateKeysTest extends TestCase
{
    use FixtureLoader;

    public function testUpgradeJSKeys()
    {
        list($parameters, $input, $output)
            = $this->loadFixture(__DIR__ .'/fixtures/rename-translate-keys.testfixture');
        $updater = (new RenameTranslateKeys())
            ->withParameters($parameters);
        $path = 'mysite/src/Class.php';

        // Build mock collection
        $code = new MockCodeCollection([
            $path => $input
        ]);
        $file = $code->itemByPath($path);
        $changset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changset);

        $this->assertFalse($changset->hasWarnings($path));
        $this->assertEquals($output, $generated);
    }
}
