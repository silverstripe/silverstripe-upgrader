<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule;

use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\AddNamespaceRule;

class AddNamespaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    protected function getFixtures()
    {
        // Get fixture from the file
        $fixture = file_get_contents(__DIR__ .'/fixtures/add-namespace.testfixture');

        list($parameters, $input, $output) = preg_split('/------+/', $fixture, 3);
        $parameters = json_decode($parameters, true);
        $input = trim($input);
        $output = trim($output);

        return [$parameters, $input, $output];
    }

    /**
     * Test applying namespaces to a folder
     */
    public function testNamespaceFolder()
    {
        list($parameters, $input, $output) = $this->getFixtures();

        // Build mock collection
        $code = new MockCodeCollection([
            'test.php' => $input
        ]);
        $file = $code->itemByPath('test.php');
        $otherfile = $code->itemByPath('otherfile.php');

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $namespacer
            ->withParameters($parameters)
            ->withRoot('');

        // Check loading namespace from config
        $this->assertEquals('Upgrader\NewNamespace', $namespacer->getNamespaceForFile($file));
        $this->assertNull($namespacer->getNamespaceForFile($otherfile));

        list($generated, $warnings) = $namespacer->upgradeFile($input, $file);

        $this->assertEquals([], $warnings);
        $this->assertEquals($output, $generated);
    }
}
