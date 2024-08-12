<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ComposerOperationsController
{
    public function installPlugin($packageName, $version = '*'): string
    {
        $this->prepareDirectory($packageName);
        return $this->runComposerCommand(['require', $packageName, '--prefer-source', '--no-cache']);
    }

    public function updatePlugin($packageName): string
    {
        $this->prepareDirectory($packageName);
        return $this->runComposerCommand(['require', $packageName, '--prefer-source']);
    }

    public function removePlugin($packageName): string
    {
        return $this->runComposerCommand(['remove', $packageName]);
    }

    public function dumpAutoload(): string
    {
        return $this->runComposerCommand(['dump-autoload']);
    }

    private function prepareDirectory($packageName): void
    {
        $path = base_path("vendor/" . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        if (!File::isDirectory($path)) {
            if (!File::makeDirectory($path, 0755, true, true)) {
                throw new \Exception("Unable to create directory: {$path}");
            }
        }
    }

    private function setPermissionsRecursively($path)
    {
        // 이 메서드를 제거하거나 로깅만 수행하도록 변경
        Log::info("Attempting to set permissions for: {$path}");
    }

    private function runComposerCommand(array $command): string
    {
        $process = new Process(array_merge(['composer'], $command), base_path());
        $process->setTimeout(300);
        $process->setEnv(['COMPOSER_HOME' => '/tmp']);

        try {
            $process->mustRun();
            return $process->getOutput();
        } catch (ProcessFailedException $exception) {
            Log::error('Composer command failed: ' . $exception->getMessage());
            Log::error('Composer output: ' . $exception->getProcess()->getOutput());
            Log::error('Composer error output: ' . $exception->getProcess()->getErrorOutput());
            throw new \Exception('Composer command failed: ' . $exception->getMessage() . "\n" . $exception->getProcess()->getErrorOutput());
        }
    }
}