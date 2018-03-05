<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\SS;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\SS\RenameTemplateLangKeys;

class RenameTemplateLangKeysTest extends TestCase
{
    use FixtureLoader;

    public function testUpgradeTemplateLangKeys()
    {
        list($parameters, $input, $output)
            = $this->loadFixture(__DIR__ .'/fixtures/rename-i18n-keys.testfixture');
        $updater = (new RenameTemplateLangKeys())
            ->withParameters($parameters);
        $path = 'mysite/templates/Test.ss';

        // Build mock collection
        $code = new MockCodeCollection([
            $path => $input
        ]);
        $file = $code->itemByPath($path);
        $changeset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changeset);

        $this->assertFalse($changeset->hasWarnings($path));
        $this->assertEquals($output, $generated);
    }
}
