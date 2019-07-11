<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\ClassToTraitRule;

class ClassToTraitTest extends BaseVisitorTest
{
    use FixtureLoader;

    /**
     * @runInSeparateProcess
     * @throws \Exception
     */
    public function testReplaceClassWithTraits()
    {
        list($parameters, $input, $expected) =
            $this->loadFixture(__DIR__ . '/../fixtures/class-to-traits.testfixture');

        $classToTraits = [
            'SS_Object' => [
                'SilverStripe\Core\Extensible'=> 'Extensible',
                'SilverStripe\Core\Injector\Injectable'=> 'Injectable',
                'SilverStripe\Core\Config\Configurable'=> 'Configurable'
            ],
            'Object' => [
                'SilverStripe\Core\Extensible'=> 'Extensible',
                'SilverStripe\Core\Injector\Injectable'=> 'Injectable',
                'SilverStripe\Core\Config\Configurable'=> 'Configurable'
            ]
        ];

        $code = new MockCodeCollection([
            'dir/test.php' => $input,
        ]);
        $classToTraitRule = new ClassToTraitRule($classToTraits);
        $changeset = new CodeChangeSet();
        $classToTraitRule->beforeUpgradeCollection($code, $changeset);
        $file = $code->itemByPath('dir/test.php');
        $generated = $classToTraitRule->upgradeFile($input, $file, $changeset);

        $this->assertEquals($generated, $expected);
    }

    /**
     * @runInSeparateProcess
     * @throws \Exception
     */
    public function testReplaceClassWithTraitsWithoutExtends()
    {
        list($parameters, $input, $expected) =
            $this->loadFixture(__DIR__ . '/../fixtures/class-to-traits-no-extends.testfixture');

        $classToTraits = [
            'SS_Object' => [
                'SilverStripe\Core\Extensible'=> 'Extensible',
                'SilverStripe\Core\Injector\Injectable'=> 'Injectable',
                'SilverStripe\Core\Config\Configurable'=> 'Configurable'
            ],
            'Object' => [
                'SilverStripe\Core\Extensible'=> 'Extensible',
                'SilverStripe\Core\Injector\Injectable'=> 'Injectable',
                'SilverStripe\Core\Config\Configurable'=> 'Configurable'
            ]
        ];

        $code = new MockCodeCollection([
            'dir/test.php' => $input,
        ]);
        $classToTraitRule = new ClassToTraitRule($classToTraits);
        $changeset = new CodeChangeSet();
        $classToTraitRule->beforeUpgradeCollection($code, $changeset);
        $file = $code->itemByPath('dir/test.php');
        $generated = $classToTraitRule->upgradeFile($input, $file, $changeset);

        $this->assertEquals($generated, $expected);
    }
}
