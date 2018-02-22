<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use SilverStripe\Upgrader\Tests\RecordingVisitor;

class SymbolContextVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testStaticMethod()
    {
        $this->scaffoldMockClass('SomeNamespace\\SomeClass');
        $this->scaffoldMockClass('SomeNamespace\\OtherClass');

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;
use SomeNamespace\OtherClass;

class MyClass {
    public function myMethod()
    {
        \$foo = SomeClass::deletedMethod();
    }
}
PHP;
        $inputItem = $this->getMockFile($input, 'MyClass.php');

        // Record traversal and get static calls
        $recorder = new RecordingVisitor();
        $this->traverseWithVisitor($inputItem, $recorder);
        $methodSymbols = $recorder->getVisitedNodesOfType(StaticCall::class);

        // Check method symbols
        $this->assertCount(1, $methodSymbols);
        $this->assertEquals(
            ['SomeNamespace\\SomeClass'],
            $methodSymbols[0]->getAttribute('contextTypes')
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstanceMethod()
    {
        $this->scaffoldMockClass('SomeNamespace\\SomeClass');
        $this->scaffoldMockClass('SomeNamespace\\OtherClass');

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;
use SomeNamespace\OtherClass;

class MyClass {
    public function myMethod()
    {
        \$foo = new SomeClass();
        \$foo->deletedMethod();
    }
    
    public function otherMethod()
    {
        \$bar = new OtherClass();
        \$bar->deletedMethod();
    }
}
PHP;
        $inputItem = $this->getMockFile($input, 'MyClass.php');


        // Record traversal and get instance method calls
        $recorder = new RecordingVisitor();
        $this->traverseWithVisitor($inputItem, $recorder);
        $methodSymbols = $recorder->getVisitedNodesOfType(MethodCall::class);

        $this->assertCount(2, $methodSymbols);

        $this->assertEquals(
            ['SomeNamespace\\SomeClass'],
            $methodSymbols[0]->getAttribute('contextTypes')
        );

        $this->assertEquals(
            ['SomeNamespace\\OtherClass'],
            $methodSymbols[1]->getAttribute('contextTypes')
        );
    }
}
