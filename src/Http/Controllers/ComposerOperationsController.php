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
        return $this->runComposerCommand(['require', $packageName, '--prefer-source']);
    }

    public function removePlugin($packageName): string
    {
        $this->removePackageDirectory($packageName);
        return $this->runComposerCommand(['remove', $packageName, '--ignore-platform-reqs']);
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

    private function removePackageDirectory($packageName): void
    {
        $path = base_path("vendor/" . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
            Log::info("Removed directory: {$path}");
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
        $process->setEnv(['COMPOSER_HOME' => '/tmp', 'GIT_TERMINAL_PROMPT' => '0']);

        try {
            $process->mustRun();
            return $process->getOutput();
        } catch (ProcessFailedException $exception) {
            Log::error('Composer command failed: ' . $exception->getMessage());
            Log::error('Composer output: ' . $exception->getProcess()->getOutput());
            Log::error('Composer error output: ' . $exception->getProcess()->getErrorOutput());
            
            // 오류 발생 시 composer.json 파일 확인
            $this->checkComposerJson($packageName);
            
            throw new \Exception('Composer command failed: ' . $exception->getMessage() . "\n" . $exception->getProcess()->getErrorOutput());
        }
    }

    private function checkComposerJson($packageName): void
    {
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
        
        if (isset($composerJson['require'][$packageName])) {
            unset($composerJson['require'][$packageName]);
            file_put_contents(base_path('composer.json'), json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::info("Removed {$packageName} from composer.json manually");
        } else {
            Log::info("{$packageName} not found in composer.json");
        }
    }
}