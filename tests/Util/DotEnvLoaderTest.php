<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\DotEnvLoader;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use M1\Env\Parser;

class DotEnvLoaderTest extends TestCase
{

    /**
     * @var  vfsStreamDirectory
     */
    private $root;

    public function setUp()
    {
        $this->root = vfsStream::setup('ss_project_root');


    }

    public function testGetInputContent()
    {
        // Let's try without any .env file
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', []);
        $this->assertEmpty($dotenv->getInputContent(), "Input content should be empty when there's no .env file");

        // Let's try with an empty .env file.
        vfsStream::newFile('.env')->withContent('')->at($this->root);
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', []);
        $this->assertEmpty($dotenv->getInputContent(), "", "Input content should be empty when the .env is empty to begin with.");

        // Let's try with an non empty .env fiel
        vfsStream::newFile('.env')->withContent('SS_ENVIRONMENT_TYPE="dev"')->at($this->root);
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', []);
        $this->assertEquals(
            $dotenv->getInputContent(),
            'SS_ENVIRONMENT_TYPE="dev"',
            "Input content should match what's in the .env file"
        );
    }

    public function testGetOutputContent()
    {
        $consts = [
            'SS_ENVIRONMENT_TYPE' => 'live',
            'SS_DEFAULT_ADMIN_USERNAME' => 'admin',
            'SS_DEFAULT_ADMIN_PASSWORD' => 'SECRET'
        ];

        // Empty .env file with some consts.
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', $consts);
        $this->assertEnvContentMatchArray($dotenv->getOutputContent(), $consts, "Output should match the const when an empty .env file is provided.");

        // .env file with some values that don't clash with our provided constant.
        vfsStream::newFile('.env')->withContent('SS_DATABASE_NAME="SS_foobar"')->at($this->root);
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', $consts);
        $this->assertEnvContentMatchArray(
            $dotenv->getOutputContent(),
            array_merge(['SS_DATABASE_NAME'=>'SS_foobar'], $consts),
            "Output should include existing values that don't conflict with new values."
        );

        // .env file with some values that clash with our provided constant.
        vfsStream::newFile('.env')
            ->withContent( <<<EOF
SS_DATABASE_NAME="SS_foobar"
SS_DEFAULT_ADMIN_USERNAME="root"
EOF
            )->at($this->root);
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', $consts);
        $this->assertEnvContentMatchArray(
            $dotenv->getOutputContent(),
            array_merge(['SS_DATABASE_NAME'=>'SS_foobar'], $consts),
            "Existing values should be overriden with new values in the getOutputContent."
        );
    }

    public function testWriteChange()
    {
        $consts = [
            'SS_ENVIRONMENT_TYPE' => 'live',
            'SS_DEFAULT_ADMIN_USERNAME' => 'admin',
            'SS_DEFAULT_ADMIN_PASSWORD' => 'SECRET'
        ];

        // Make sure the constructor doesn't auto-write the file.
        $dotenv = new DotEnvLoader($this->root->url() . '/.env', $consts);
        $this->assertFalse($this->root->hasChild('.env'), '`__construct` should not write .env file.');

        // Make sure writeChange change write a file.
        $dotenv->writeChange();
        $this->assertTrue($this->root->hasChild('.env'), '`writeChange` should have written change.');

        // Make sure the written content of .env matches the getOutputContent.
        $this->assertEquals(
            $this->root->getChild('.env')->getContent(),
            $dotenv->getOutputContent(),
            "`writeChange` should write the same content from `getOutputContent`."
        );
    }

    /**
     * Simple assert to compare that a .env string to a an array of constant.
     * @param  string $dotEnvContent
     * @param  array  $consts
     * @param  string $message
     */
    private function assertEnvContentMatchArray(string $dotEnvContent, array $consts, $message="")
    {
        $constFromString = Parser::parse($dotEnvContent);
        $this->assertEquals($constFromString, $consts, $message);
    }

}
