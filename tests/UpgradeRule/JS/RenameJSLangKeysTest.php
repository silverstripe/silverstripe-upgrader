<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\JS;

use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\JS\RenameJSLangKeys;

class RenameJSLangKeysTest extends PHPUnit_Framework_TestCase
{
    use FixtureLoader;

    public function testUpgradeJSKeys()
    {
        list($parameters, $input, $output)
            = $this->loadFixture(__DIR__ .'/fixtures/rename-js-keys.testfixture');
        $updater = (new RenameJSLangKeys())->withParameters($parameters);
        $path = 'mysite/lang/src/en.js';

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

    public function dataTestAppliesTo()
    {
        return [
            // True
            [ 'mysite/lang/src/en.js', true ],
            [ 'mysite/lang/src/en.json', true ],
            // False
            [ 'mysite/lang/en.js', false ],
            [ 'mysite/lang/en.json', false ],
            [ 'mysite/js/script.js', false ],
            [ 'mysite/lang/src/en.php', false ],
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
        $updater = new RenameJSLangKeys();
        $this->assertEquals($result, $updater->appliesTo($file));
    }
}
