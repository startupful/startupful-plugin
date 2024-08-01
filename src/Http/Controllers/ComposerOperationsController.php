<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ComposerOperationsController
{
    private function forceDeleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        if (is_link($dir)) {
            $this->ensureWritePermissions(readlink($dir));
            return unlink($dir);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $this->ensureWritePermissions($file->getRealPath());

            if ($file->isDir() && !is_link($file->getPathname())) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        $this->ensureWritePermissions($dir);
        rmdir($dir);
    }

    public function removePackageFromComposerJson($packageName): void
    {
        $composerJsonPath = base_path('composer.json');
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        unset($composerJson['require'][$packageName]);
        file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function runComposerUpdate($packageName): void
    {
        $this->runComposerCommand(['update', $packageName, '--with-all-dependencies']);
    }

    public function installNewVersion($packageName, $version): void
    {
        $this->runComposerCommand(['require', "{$packageName}:{$version}"]);
    }

    public function removePackage($packageName): string
    {
        try {
            // 먼저 composer.json에서 패키지를 제거
            $this->removePackageFromComposerJson($packageName);

            // composer remove 명령 실행
            $process = $this->runComposerCommand(['remove', $packageName, '--no-update']);
            
            // composer update 실행
            $this->runComposerCommand(['update']);
            
            // 캐시 및 오토로더 정리
            $this->clearComposerCache();
            $this->dumpAutoload();
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('optimize:clear');

            Log::info("Package {$packageName} removed successfully. Output: " . $process->getOutput());
            return "Package {$packageName} removed successfully.";
        } catch (ProcessFailedException $e) {
            $errorOutput = $e->getProcess()->getErrorOutput();
            Log::error("Failed to remove package {$packageName}. Error: " . $errorOutput);
            throw new \Exception("Failed to remove package {$packageName}. Error: " . $errorOutput);
        }
    }

    public function updatePackage($packageName, $version = null): string
    {
        try {
            $vendorDir = base_path('vendor/' . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
            $tempDir = sys_get_temp_dir() . '/' . uniqid('plugin_update_');
            
            // 기존 디렉토리를 임시 디렉토리로 복사
            if (is_dir($vendorDir)) {
                $this->copyDirectory($vendorDir, $tempDir);
                $this->forceDeleteDirectory($vendorDir);
            }

            $command = ['require', "{$packageName}:{$version}"];

            $process = $this->runComposerCommand($command);
            
            // 업데이트 성공 시 임시 디렉토리 삭제
            if (is_dir($tempDir)) {
                $this->forceDeleteDirectory($tempDir);
            }
            
            Log::info("Package {$packageName} updated successfully. Output: " . $process->getOutput());
            return "Package {$packageName} updated successfully.";
        } catch (ProcessFailedException $e) {
            // 실패 시 임시 디렉토리를 원래 위치로 복원
            if (is_dir($tempDir) && !is_dir($vendorDir)) {
                $this->copyDirectory($tempDir, $vendorDir);
                $this->forceDeleteDirectory($tempDir);
            }
            
            $errorOutput = $e->getProcess()->getErrorOutput();
            Log::error("Failed to update package {$packageName}. Error: " . $errorOutput);
            throw new \Exception("Failed to update package {$packageName}. Error: " . $errorOutput);
        }
    }

    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath);
                }
            } else {
                if (is_link($item->getPathname())) {
                    symlink(readlink($item->getPathname()), $targetPath);
                } else {
                    copy($item, $targetPath);
                }
            }
        }
    }

    protected function runComposerCommand(array $command): Process
    {
        $env = getenv();
        $env['HOME'] = base_path();
        $env['COMPOSER_HOME'] = sys_get_temp_dir() . '/.composer';

        $process = new Process(array_merge(['composer'], $command), base_path(), $env);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    public function clearComposerCache(): void
    {
        $this->runComposerCommand(['clear-cache']);
    }

    public function dumpAutoload(): void
    {
        $this->runComposerCommand(['dump-autoload']);
    }

    private function ensureWritePermissions($path)
    {
        if (is_link($path)) {
            $realPath = readlink($path);
            if (!is_writable($realPath)) {
                @chmod($realPath, 0777);
                if (!is_writable($realPath)) {
                    throw new \Exception("Unable to set write permissions on symlinked path: $path (real path: $realPath)");
                }
            }
        } elseif (!is_writable($path)) {
            @chmod($path, 0777);
            if (!is_writable($path)) {
                throw new \Exception("Unable to set write permissions on: $path");
            }
        }
    }
}