<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PropertyWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class PropertyWarningsVisitorTest extends BaseVisitorTest
{
    public function testInstancePropDefinitionWithoutClass()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespaced\NamespacedClass;

class MyClass
{
    protected \$removedInstanceProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = new MyClass();
        \$foo->removedInstanceProp;
    }
    
    function noMatch()
    {
        \$bar = new MyOtherClass();
        \$bar->removedInstanceProp;
    }
}
PHP;

        $visitor = new PropertyWarningsVisitor([
            (new ApiChangeWarningSpec('removedInstanceProp', 'Test instance prop'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(3, $warnings);

        $this->assertContains('Test instance prop', $warnings[0]->getMessage());
        $this->assertContains('protected $removedInstanceProp', $this->getLineForWarning($input, $warnings[0]));

        $this->assertContains('Test instance prop', $warnings[1]->getMessage());
        $this->assertContains('$foo->removedInstanceProp', $this->getLineForWarning($input, $warnings[1]));

        $this->assertContains('Test instance prop', $warnings[2]->getMessage());
        $this->assertContains('$bar->removedInstanceProp', $this->getLineForWarning($input, $warnings[2]));
    }

    public function testInstancePropDefinitionWithClass()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespaced\NamespacedClass;

class MyClass
{
    protected \$removedInstanceProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = new MyClass();
        \$foo->removedInstanceProp;
    }
    
    function noMatch()
    {
        \$bar = new MyOtherClass();
        \$bar->removedInstanceProp;
    }
}
PHP;

        $visitor = new PropertyWarningsVisitor([
            (new ApiChangeWarningSpec('MyNamespace\\MyClass->removedInstanceProp', 'Test instance prop'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Test instance prop', $warnings[0]->getMessage());
        $this->assertContains('protected $removedInstanceProp', $this->getLineForWarning($input, $warnings[0]));

        $this->assertContains('Test instance prop', $warnings[1]->getMessage());
        $this->assertContains('$foo->removedInstanceProp', $this->getLineForWarning($input, $warnings[1]));
    }

    public function testStaticPropDefinitionWithClass()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespaced\NamespacedClass;

class MyClass
{
    protected static \$removedStaticProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = MyClass::\$removedStaticProp;
        \$noMatch = MyOtherClass::\$removedStaticProp;
    }
}
PHP;

        $visitor = new PropertyWarningsVisitor([
            (new ApiChangeWarningSpec('MyNamespace\\MyClass::removedStaticProp', 'Test staticprop'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Test staticprop', $warnings[0]->getMessage());
        $this->assertContains(
            'protected static $removedStaticProp = true',
            $this->getLineForWarning($input, $warnings[0])
        );

        $this->assertContains('Test staticprop', $warnings[1]->getMessage());
        $this->assertContains('MyClass::$removedStaticProp', $this->getLineForWarning($input, $warnings[1]));
    }
}
