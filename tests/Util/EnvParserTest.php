<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\EnvParser;
use SilverStripe\Upgrader\CodeCollection\DiskItem;

class EnvTest extends TestCase
{
    public function testDefined()
    {
        $parser = new EnvParser($this->loadTestFile('singleDefine'), '/var/www');

        $this->assertTrue($parser->isValid(), "`define` statement should be valid.");
    }

    public function testConditional()
    {
        $parser = new EnvParser($this->loadTestFile('conditional'), '/var/www');

        $this->assertFalse($parser->isValid(), "`if` statement should not be valid.");
    }


    public function testReadOfConst()
    {
        $parser = new EnvParser($this->loadTestFile('singleDefine'), '/var/www');
        $this->assertEquals(
            $parser->getSSFourEnv(),
            ['SS_HELLO' => 'World'],
            '`getSSFourEnv` should get constants defined in environment file.'
        );

        $parser = new EnvParser($this->loadTestFile('fileToUrlMappingOnly'), '/var/www');
        $this->assertEquals(
            $parser->getSSFourEnv(),
            ['SS_BASE_URL' => 'http://simon.geek.nz'],
            '`getSSFourEnv` should read base URL from environment file.'
        );


    }

    /**
     * Utility method to load our sample env file into a DiskItem.
     * @param  string $filename name of the file to load without the extension.
     * @return DiskItem
     */
    private function loadTestFile(string $filename): DiskItem
    {
        return new DiskItem(
            __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'ssEnvFile',
            $filename . '.php'
        );
    }
}
