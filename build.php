#!/usr/bin/env php
<?php

use Secondtruth\Compiler\Compiler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// Suppress warning for `token_get_all` when compiling
error_reporting(E_ALL & ~E_COMPILE_WARNING);

if(!defined('PACKAGE_ROOT')) define('PACKAGE_ROOT', dirname(__FILE__) . '/');
if(!defined('BUILD_FOLDER')) define('BUILD_FOLDER', tempnam(sys_get_temp_dir(),'') );

require_once(PACKAGE_ROOT . '/vendor/autoload.php');

$fs = new Filesystem();

// Make sure our build folder exists
if ($fs->exists(BUILD_FOLDER)) { $fs->remove(BUILD_FOLDER); }

$fs->mkdir(BUILD_FOLDER);

// Set up our build folder
$fs->mirror(PACKAGE_ROOT . 'bin', BUILD_FOLDER . '/bin');
$fs->mirror(PACKAGE_ROOT . 'src', BUILD_FOLDER . '/src');
$fs->copy(PACKAGE_ROOT . 'composer.json', BUILD_FOLDER . '/composer.json');
$fs->copy(PACKAGE_ROOT . 'composer.lock', BUILD_FOLDER . '/composer.lock');

// Install dependencies
$composerStatement = sprintf(
    'composer install --prefer-dist --no-dev --optimize-autoloader --optimize-autoloader --working-dir=%s',
    BUILD_FOLDER
);
$process = new Process($composerStatement);
$process->run();

// Compile the executable
$compiler = new Compiler(BUILD_FOLDER);
$compiler->addIndexFile('bin/upgrade-code');
$compiler->addDirectory('src');
$compiler->addDirectory('vendor');

$compiledPath = PACKAGE_ROOT . "upgrade-code.phar";
if ($fs->exists($compiledPath)) { $fs->remove($compiledPath);}
$compiler->compile($compiledPath);
$fs->chmod($compiledPath, 0755);

// Clean up
$fs->remove(BUILD_FOLDER);
$process = new Process($compiledPath);
try {
    $process->mustRun();
    echo "Phar built to: $compiledPath \n";
} catch (ProcessFailedException $ex) {
    echo "The generated executable is broken.\nDO NOT PUBLISH!!!\n\n";
    echo $ex->getMessage();
    exit(1);
}

