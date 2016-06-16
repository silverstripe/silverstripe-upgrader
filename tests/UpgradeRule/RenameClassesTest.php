<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule;

use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\RenameClasses;

class RenameClassesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    protected function getFixtures()
    {
        // Get fixture from the file
        $fixture = file_get_contents(__DIR__ .'/fixtures/rename-classes.testfixture');
        list($parameters, $input, $output) = preg_split('/------+/', $fixture, 3);
        $parameters = json_decode($parameters, true);
        $input = trim($input);
        $output = trim($output);

        return [$parameters, $input, $output];
    }

    public function testNamespaceAddition()
    {
        list($parameters, $input, $output) = $this->getFixtures();
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
