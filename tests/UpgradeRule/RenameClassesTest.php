<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule;

use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\RenameClasses;

class RenameClassesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $file
     * @return array
     */
    protected function getFixtures($file)
    {
        // Get fixture from the file
        $fixture = file_get_contents(__DIR__ .'/fixtures/'.$file);
        list($parameters, $input, $output) = preg_split('/------+/', $fixture, 3);
        $parameters = json_decode($parameters, true);
        $input = trim($input);
        $output = trim($output);

        return [$parameters, $input, $output];
    }

    /**
     * @return array
     */
    public function provideTests()
    {
        return [
            ['rename-classes.testfixture'],
            ['rename-simple.testfixture'],
        ];
    }

    /**
     * @dataProvider provideTests
     * @param $fixture
     */
    public function testNamespaceAddition($fixture)
    {
        list($parameters, $input, $output) = $this->getFixtures($fixture);
        $updater = (new RenameClasses())->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'test.php' => $input
        ]);
        $file = $code->itemByPath('test.php');
        $changset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changset);

        $this->assertFalse($changset->hasWarnings('test.php'));
        $this->assertEquals($output, $generated);
    }
}
