<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\LegacyEnvParser;
use SilverStripe\Upgrader\CodeCollection\DiskItem;

class LegacyEnvTest extends TestCase
{
    ########################
    # Valid Tests          #
    ########################


    public function testDefine()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('singleDefine', true), '/var/www');

        $this->assertTrue($parser->isValid(), "`define` statement with scalar should be valid.");
    }

    public function testComment()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('commentsOnly', true), '/var/www');

        $this->assertTrue($parser->isValid(), "Comments should be valid.");
    }

    public function testFileUrlMapping()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('fileToUrlMappingOnly', true), '/var/www');

        $this->assertTrue($parser->isValid(), "Assignning to `\$_FILE_TO_URL_MAPPING` should be valid.");
    }

    ########################
    # Invalid Tests        #
    ########################

    public function testConditional()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('conditional', false), '/var/www');

        $this->assertFalse($parser->isValid(), "`if` statement should not be valid.");
    }

    public function testDefineWithNonScallar()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('defineNonScalar', false), '/var/www');

        $this->assertFalse($parser->isValid(), "`define` statement with non scalar should be invalid.");
    }


    ########################
    # Other                #
    ########################

    public function testReadOfConst()
    {
        $parser = new LegacyEnvParser($this->loadTestFile('singleDefine', true), '/var/www');
        $this->assertEquals(
            $parser->getSSFourEnv(),
            ['SS_HELLO' => 'World'],
            '`getSSFourEnv` should get constants defined in environment file.'
        );

        $parser = new LegacyEnvParser($this->loadTestFile('fileToUrlMappingOnly', true), '/var/www');
        $this->assertEquals(
            $parser->getSSFourEnv(),
            ['SS_BASE_URL' => 'http://simon.geek.nz'],
            '`getSSFourEnv` should read base URL from environment file.'
        );
    }

    public function testMalformed()
    {
        $this->expectException(\Exception::class);
        $parser = new LegacyEnvParser($this->loadTestFile('malformed', false), '/var/www');
        $parser->getSSFourEnv();
    }

    /**
     * Utility method to load our sample env file into a DiskItem.
     * @param  string $filename name of the file to load without the extension.
     * @return DiskItem
     */
    private function loadTestFile(string $filename, bool $valid): DiskItem
    {
        return new DiskItem(
            __DIR__ . DIRECTORY_SEPARATOR .
            'fixtures' . DIRECTORY_SEPARATOR .
            'ssEnvFile' . DIRECTORY_SEPARATOR .
            ($valid ? 'valid' : 'invalid'),
            $filename . '.php'
        );
    }
}
