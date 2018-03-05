<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\PropertyWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\MutableSource;

class PropertyWarningsVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testInstancePropDefinitionWithoutClass()
    {
        $this->scaffoldMockClass('SomeNamespace\\NamespacedClass');

        $myCode = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

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

        $input = $this->getMockFile($myCode);
        $source = new MutableSource($input->getContents());
        $visitor = new PropertyWarningsVisitor([
            new ApiChangeWarningSpec('removedInstanceProp', [
                'message' => 'Test instance prop',
                'replacement' => 'newInstanceProp',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(3, $warnings);

        $this->assertContains('Test instance prop', $warnings[0]->getMessage());
        $this->assertContains('protected $removedInstanceProp', $this->getLineForWarning($myCode, $warnings[0]));

        $this->assertContains('Test instance prop', $warnings[1]->getMessage());
        $this->assertContains('$foo->removedInstanceProp', $this->getLineForWarning($myCode, $warnings[1]));

        $this->assertContains('Test instance prop', $warnings[2]->getMessage());
        $this->assertContains('$bar->removedInstanceProp', $this->getLineForWarning($myCode, $warnings[2]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

class MyClass
{
    protected \$newInstanceProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = new MyClass();
        \$foo->newInstanceProp;
    }
    
    function noMatch()
    {
        \$bar = new MyOtherClass();
        \$bar->newInstanceProp;
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstancePropDefinitionWithClass()
    {
        $this->scaffoldMockClass('SomeNamespace\\NamespacedClass');
        $this->scaffoldMockClass('MyNamespace\\MyClass');

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

class SubClass extends MyClass
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

        $inputFile = $this->getMockFile($input);
        $source = new MutableSource($inputFile->getContents());
        $visitor = new PropertyWarningsVisitor([
            new ApiChangeWarningSpec('MyNamespace\\MyClass->removedInstanceProp', [
                'message' => 'Test instance prop',
                'replacement' => 'newInstanceProp',
            ])
        ], $source, $inputFile);

        $this->traverseWithVisitor($source, $inputFile, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Test instance prop', $warnings[0]->getMessage());
        $this->assertContains('protected $removedInstanceProp', $this->getLineForWarning($input, $warnings[0]));

        $this->assertContains('Test instance prop', $warnings[1]->getMessage());
        $this->assertContains('$foo->removedInstanceProp', $this->getLineForWarning($input, $warnings[1]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

class SubClass extends MyClass
{
    protected \$newInstanceProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = new MyClass();
        \$foo->newInstanceProp;
    }
    
    function noMatch()
    {
        \$bar = new MyOtherClass();
        \$bar->removedInstanceProp;
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testStaticPropDefinitionWithClass()
    {
        $this->scaffoldMockClass('SomeNamespace\\NamespacedClass');
        $this->scaffoldMockClass('MyNamespace\\MyClass');
        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

class SubClass extends MyClass
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

        $inputFile = $this->getMockFile($input);
        $source = new MutableSource($inputFile->getContents());
        $visitor = new PropertyWarningsVisitor([
            new ApiChangeWarningSpec('MyNamespace\\MyClass::removedStaticProp', [
                'message' => 'Test staticprop',
                'replacement' => 'newStaticProp',
            ])
        ], $source, $inputFile);

        $this->traverseWithVisitor($source, $inputFile, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(2, $warnings);

        $this->assertContains('Test staticprop', $warnings[0]->getMessage());
        $this->assertContains(
            'protected static $removedStaticProp = true',
            $this->getLineForWarning($input, $warnings[0])
        );

        $this->assertContains('Test staticprop', $warnings[1]->getMessage());
        $this->assertContains('MyClass::$removedStaticProp', $this->getLineForWarning($input, $warnings[1]));

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\NamespacedClass;

class SubClass extends MyClass
{
    protected static \$newStaticProp = true;
}

class MyOtherClass
{
    function useProp()
    {
        \$foo = MyClass::\$newStaticProp;
        \$noMatch = MyOtherClass::\$removedStaticProp;
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testIgnoresDynamicVars()
    {
        $input = <<<PHP
<?php

namespace MyNamespace;

class MyClass
{
    function useProp()
    {
        \$match = new MyClass();
        \$match->removedProp;
        
        \$noMatch = new MyClass();
        \$noMatch->\$removedProp;
    }
}
PHP;

        $inputFile = $this->getMockFile($input);
        $source = new MutableSource($inputFile->getContents());
        $visitor = new PropertyWarningsVisitor([
            new ApiChangeWarningSpec('removedProp', [
                'message' => 'Test removedProp',
                'replacement' => 'newProp',
            ])
        ], $source, $inputFile);

        $this->traverseWithVisitor($source, $inputFile, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test removedProp', $warnings[0]->getMessage());
        $this->assertContains(
            '$match->removedProp',
            $this->getLineForWarning($input, $warnings[0])
        );


        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

class MyClass
{
    function useProp()
    {
        \$match = new MyClass();
        \$match->newProp;
        
        \$noMatch = new MyClass();
        \$noMatch->\$removedProp;
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }
}
