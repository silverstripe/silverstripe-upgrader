<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\SymbolContextVisitor;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

class SymbolContextVisitorTest extends BaseVisitorTest
{
    public function testStaticMethod()
    {
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

        $visitor = new SymbolContextVisitor();
        $this->traverseWithVisitor($input, $visitor);

        $symbols = $visitor->getSymbols();
        $methodSymbols = array_values(array_filter($symbols, function($symbol) {
            return ($symbol instanceof StaticCall);
        }));

        $this->assertCount(1, $methodSymbols);

        $this->assertEquals(
            $methodSymbols[0]->getAttribute('symbolContext')['uses'],
            ['SomeNamespace\\SomeClass','SomeNamespace\\OtherClass']
        );
        $this->assertEquals(
            $methodSymbols[0]->getAttribute('symbolContext')['staticClass'],
            'SomeNamespace\\SomeClass'
        );
    }

    public function testInstanceMethod()
    {
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

        $visitor = new SymbolContextVisitor();
        $this->traverseWithVisitor($input, $visitor);

        $symbols = $visitor->getSymbols();
        $methodSymbols = array_values(array_filter($symbols, function($symbol) {
            return ($symbol instanceof MethodCall);
        }));

        $this->assertCount(2, $methodSymbols);

        $this->assertEquals(
            $methodSymbols[0]->getAttribute('symbolContext')['uses'],
            ['SomeNamespace\\SomeClass','SomeNamespace\\OtherClass']
        );
        $this->assertNull(
            $methodSymbols[0]->getAttribute('symbolContext')['staticClass']
        );
        $this->assertEquals(
            $methodSymbols[0]->getAttribute('symbolContext')['methodClasses'],
            ['SomeNamespace\\SomeClass']
        );

        $this->assertEquals(
            $methodSymbols[1]->getAttribute('symbolContext')['uses'],
            ['SomeNamespace\\SomeClass','SomeNamespace\\OtherClass']
        );
        $this->assertNull(
            $methodSymbols[1]->getAttribute('symbolContext')['staticClass']
        );
        $this->assertEquals(
            $methodSymbols[1]->getAttribute('symbolContext')['methodClasses'],
            ['SomeNamespace\\OtherClass']
        );
    }
}
