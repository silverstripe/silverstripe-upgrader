<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Tests\Console\MockAutomatedCommand;
use SilverStripe\Upgrader\Tests\Console\MockFailedAutomatedCommand;
use SilverStripe\Upgrader\Util\CommandRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandRunnerTest extends TestCase
{

    public function testRun()
    {
        $app = new Application();
        $first = new MockAutomatedCommand('first');
        $second = new MockAutomatedCommand('second');

        $app->add($first);
        $app->add($second);

        $runner = new CommandRunner();
        $runner->run($app, ['first', 'second'], [], new BufferedOutput());

        $this->assertTrue(
            $first->state['execute'],
            'first command should have been executed.'
        );

        $this->assertTrue(
            $second->state['execute'],
            'second command should have been executed.'
        );

        $args = $first->updatedArguments();
        $this->assertEquals(1, $args['--exec'], 'exec should have been initialise by first.');

        $args = $second->updatedArguments();
        $this->assertEquals(2, $args['--exec'], 'exec should have been relayed to second and incremented.');
    }

    public function testFailedRun()
    {
        $this->expectException(RuntimeException::class);

        $app = new Application();
        $bad = new MockFailedAutomatedCommand('bad');
        $good = new MockAutomatedCommand('good');

        $app->add($bad);
        $app->add($good);

        $runner = new CommandRunner();
        $runner->run($app, ['bad', 'good'], ['--exec' => 0], new BufferedOutput());
    }
}
