<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ConstantWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class ConstantWarningsVisitorTest extends BaseVisitorTest
{
    public function testGlobalConstantAssignment()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

class MyClass
{
    function assignConstant()
    {
        \$foo = 'before' . REMOVED_CONSTANT . 'after';
    }
}
PHP;

        $visitor = new ConstantWarningsVisitor([
            (new ApiChangeWarningSpec('REMOVED_CONSTANT', 'Test REMOVED_CONSTANT'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            '\'before\' . REMOVED_CONSTANT . \'after\'',
            $this->getLineForWarning($input, $warnings[0])
        );
    }

    public function testClassConstant()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass
{
    function assignConstant()
    {
        \$ignore = REMOVED_CONSTANT;
        
        echo(SomeClass::REMOVED_CONSTANT);
    }
}
PHP;

        $visitor = new ConstantWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass::REMOVED_CONSTANT', 'Test REMOVED_CONSTANT'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            'SomeClass::REMOVED_CONSTANT',
            $this->getLineForWarning($input, $warnings[0])
        );
    }
}
