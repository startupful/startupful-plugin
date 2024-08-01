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
        return $this->runComposerCommand(['require', $packageName, '--prefer-source']);
    }

    public function updatePlugin($packageName): string
    {
        $this->prepareDirectory($packageName);
        return $this->runComposerCommand(['update', $packageName, '--prefer-source']);
    }

    public function removePlugin($packageName): string
    {
        return $this->runComposerCommand(['remove', $packageName]);
    }

    private function prepareDirectory($packageName): void
    {
        $path = base_path("vendor/" . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        if (!File::isDirectory($path)) {
            if (!File::makeDirectory($path, 0755, true)) {
                throw new \Exception("Unable to create directory: {$path}");
            }
        }

        // Set directory permissions
        chmod($path, 0755);
        $this->setPermissionsRecursively($path);
    }

    private function setPermissionsRecursively($path)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $item) {
            chmod($item, 0755);
        }
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