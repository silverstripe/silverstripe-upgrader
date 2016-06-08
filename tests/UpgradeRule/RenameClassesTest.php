<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule;

use SilverStripe\Upgrader\UpgradeRule\RenameClasses;

class RenameClassesTest extends \PHPUnit_Framework_TestCase
{
    public function testNamespaceAddition()
    {

        // Get fixture from the file
        $fixture = file_get_contents(__DIR__ .'/fixtures/rename-classes.testfixture');
        list($parameters, $input, $output) = preg_split('/------+/', $fixture, 3);
        $parameters = json_decode($parameters, true);
        $input = trim($input);
        $output = trim($output);

        $updater = (new RenameClasses())->withParameters($parameters);

        list($generated, $warnings) = $updater->upgradeFile($input, 'test.php');

        $this->assertEquals([], $warnings);
        $this->assertEquals($output, $generated);
    }
}
