<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\JS;

use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\YML\UpdateConfigClasses;

class UpdateConfigClassesTest extends PHPUnit_Framework_TestCase
{
    use FixtureLoader;

    public function testUpgradeConfig()
    {
        list($parameters, $input, $output)
            = $this->loadFixture(__DIR__ .'/fixtures/upgrade-config-classes.testfixture');
        $updater = (new UpdateConfigClasses())
            ->withParameters($parameters);
        $path = 'mysite/_config/settings.yml';

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
            [ 'mysite/_config/settings.yml', true ],
            [ 'mysite/_config/config.yaml', true ],
            // False
            [ 'mysite/_config.php', false ],
            [ 'mysite/_config/config.php', false ],
            [ 'mysite/_config.js', false ],
            [ 'mysite/config.php', false ],
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
        $updater = new UpdateConfigClasses();
        $this->assertEquals($result, $updater->appliesTo($file));
    }
}
