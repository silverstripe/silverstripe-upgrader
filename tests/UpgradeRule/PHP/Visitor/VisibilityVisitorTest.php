<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\VisibilityVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

class VisibilityVisitorTest extends BaseVisitorTest
{
    use FixtureLoader;

    /**
     * @runInSeparateProcess
     * @throws \Exception
     */
    public function testUpdateVisibility()
    {
        list($parameters, $input, $expected) =
            $this->loadFixture(__DIR__ . '/../fixtures/update-visibility.testfixture');

        $inputFile = $this->getMockFile($input);
        $source = new MutableSource($inputFile->getContents());

        $visibilities = [
            'Living::db' => [
                'visibility' => 'private'
            ],
            'Animal->myMethod' => [
                'visibility' => 'protected'
            ]
        ];
        $this->traverseWithVisitor($source, $inputFile, new VisibilityVisitor($source, $visibilities));

        // Ensure rewrite works
        $actual = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $expected);
        $this->assertEquals($actual, $expected);
    }
}
