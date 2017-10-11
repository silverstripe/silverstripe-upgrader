<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\MethodWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class MethodWarningsVisitorTest extends BaseVisitorTest
{
    public function testGlobalMethod()
    {

        $input = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function otherMethod()
    {
        return true;
    }
    
    public function removedMethod()
    {
        // Should be ignored
        \$removedMethod = true;
        return \$removedMethod;
    }

    public function useRemovedMethod()
    {
        \$obj = new stdClass();
        \$obj->removedMethod();
        
        GlobalClass::removedMethod();
        SomeClass::removedMethod();
    }
}
PHP;

        $visitor = new MethodWarningsVisitor([
            (new ApiChangeWarningSpec('removedMethod()', 'Test global method'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(4, $warnings);

        $this->assertContains('Test global method', $warnings[0]->getMessage());
        $this->assertContains('function removedMethod', $this->getLineForWarning($input, $warnings[0]));

        $this->assertContains('Test global method', $warnings[1]->getMessage());
        $this->assertContains('$obj->removedMethod()', $this->getLineForWarning($input, $warnings[1]));

        $this->assertContains('Test global method', $warnings[2]->getMessage());
        $this->assertContains('GlobalClass::removedMethod()', $this->getLineForWarning($input, $warnings[2]));

        $this->assertContains('Test global method', $warnings[3]->getMessage());
        $this->assertContains('SomeClass::removedMethod();', $this->getLineForWarning($input, $warnings[3]));
    }

    public function testStaticMethodWithClassQualifier()
    {

        $input = <<<PHP
<?php

use SomeNamespace\SomeClass;

class MyClass
{
    public function staticInvocation()
    {
        \$foo = GlobalClass::removedMethod();
    }
    
    public function staticNamespacedInvocation()
    {
        return SomeClass::removedMethod();
    }
}
PHP;

        $visitor = new MethodWarningsVisitor([
            (new ApiChangeWarningSpec('GlobalClass::removedMethod()', 'Error in GlobalClass::removedMethod()')),
            (new ApiChangeWarningSpec(
                'SomeNamespace\\SomeClass::removedMethod()',
                'Error in SomeNamespace\\SomeClass::removedMethod()'
            )),
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Error in GlobalClass::removedMethod()', $warnings[0]->getMessage());
        $this->assertContains('$foo = GlobalClass::removedMethod();', $this->getLineForWarning($input, $warnings[0]));

        $this->assertContains('Error in SomeNamespace\\SomeClass::removedMethod()', $warnings[1]->getMessage());
        $this->assertContains('return SomeClass::removedMethod()', $this->getLineForWarning($input, $warnings[1]));
    }
}
