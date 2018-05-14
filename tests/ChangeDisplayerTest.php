<?php

namespace SilverStripe\Upgrader\Tests;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\ChangeDisplayer;
use Symfony\Component\Console\Output\BufferedOutput;

class CodeChangeSetTest extends TestCase
{

    protected function fixture()
    {
        $c = new CodeChangeSet();
        $c->addFileChange('test1.php', 'foo', 'fo');
        $c->addWarning('test1.php', 15, 'something fishy');
        $c->addWarning('test2.php', 20, 'something to do');
        $c->addFileChange('test3.php', 'bar', 'ba');
        $c->addFileChange('subdir/test3.php', 'baz', 'ba');

        $c->move('moveTo.txt', 'differentLocation.txt');
        $c->addFileChange('brandNewFile.txt', 'new content', false);
        $c->remove('removed-file.txt');
        $c->addFileChange('file-with-same-content.txt', 'no-change', 'no-change');

        $c->addFileChange(
            'moveAndChange.txt',
            "Some\nNew\nContent",
            "Some\nOld\nContent",
            'changeAndMove.txt'
        );

        return $c;
    }

    public function testDisplayChanges()
    {
        $out = new BufferedOutput();
        $changeDisplayer = new ChangeDisplayer();
        $changeDisplayer->displayChanges($out, $this->fixture());

        $this->assertEquals(
            $this->expected,
            $out->fetch()
        );
    }

    private $expected = <<<EOF
modified:	test1.php
@@ -1,1 +1,1 @@
-fo
+foo

Warnings for test1.php:
 - test1.php:15 something fishy
unchanged:	test2.php
Warnings for test2.php:
 - test2.php:20 something to do
modified:	test3.php
@@ -1,1 +1,1 @@
-ba
+bar

modified:	subdir/test3.php
@@ -1,1 +1,1 @@
-ba
+baz

renamed:	moveTo.txt -> differentLocation.txt
new file:	brandNewFile.txt
@@ -1,1 +1,1 @@
-
+new content

deleted:	removed-file.txt
unchanged:	file-with-same-content.txt
renamed:	moveAndChange.txt -> changeAndMove.txt
@@ -1,3 +1,3 @@
 Some
-Old
+New
 Content


EOF;
}
