<?php

namespace SilverStripe\Upgrader\Tests\Composer;

use SilverStripe\Upgrader\Composer\ComposerFile;
use SilverStripe\Upgrader\Composer\ComposerInterface;
use Symfony\Component\Console\Exception\RuntimeException;

class MockComposer implements ComposerInterface
{

    public $isValid = true;

    public function validate(string $path, bool $throwException = false): bool
    {
        return $this->isValid;
    }

    public function initTemporarySchema(): ComposerFile
    {
        throw new RuntimeException('Not implemented');
    }

    public function require(
        string $package,
        string $constraint = '',
        string $workingDir = '',
        bool $showFeedback = false
    ): void {
        // TODO: Implement require() method.
    }

    public function update(
        string $workingDir = '',
        bool $showFeedback = false
    ): void {
        // TODO: Implement update() method.
    }

    public function remove(string $package, string $workingDir = ''): void
    {
        // TODO: Implement remove() method.
    }

    public function install(string $workingDir = ''): void
    {
        // TODO: Implement install() method.
    }


    public $showOutput = [];
    public function show(string $workingDir = ''): array
    {
        return $this->showOutput;
    }

    public $cacheDir = '';
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }
}
