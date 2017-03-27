<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\JS;

use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\YML\RenameYMLLangKeys;

class RenameYMLLangKeysTest extends PHPUnit_Framework_TestCase
{
    use FixtureLoader;

    public function testUpgradeConfig()
    {
        list($parameters, $input, $output)
            = $this->loadFixture(__DIR__ .'/fixtures/rename-i18n-keys.testfixture');
        $updater = (new RenameYMLLangKeys())
            ->withParameters($parameters);
        $path = 'mysite/lang/zh_CN.yml';

        // Build mock collection
        $code = new MockCodeCollection([
            $path => $input
        ]);
        $file = $code->itemByPath($path);
        $changset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changset);

        $this->assertFalse($changset->hasWarnings($path));
        $this->assertEquals(trim($output), trim($generated));
    }

    public function dataTestAppliesTo()
    {
        return [
            // True
            [ 'mysite/lang/zh_CN.yml', true ],
            [ 'mysite/lang/en.yaml', true ],
            // False
            [ 'mysite/notlang/zh_CN.yml', false ],
            [ 'mysite/lang/zh_CN.php', false ],
            [ 'mysite/lang/src/en.js', false ],
        ];
    }

    /**
     * @dataProvider dataTestAppliesTo
     * @param string $path
     * @param bool $result
     */
    public function testAppliesTo($path, $result)
    {
        $code = new MockCodeCollection([
            $path => 'dummy'
        ]);
        $file = $code->itemByPath($path);
        $updater = new RenameYMLLangKeys();
        $this->assertEquals($result, $updater->appliesTo($file));
    }
}
