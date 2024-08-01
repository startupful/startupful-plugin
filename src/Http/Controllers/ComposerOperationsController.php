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

    private function isDevMode(): bool
    {
        return app()->environment('local', 'development');
    }

    private function getPackagePath($packageName): string
    {
        $vendorPath = base_path('vendor/' . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        $localPath = base_path('packages/' . str_replace('/', DIRECTORY_SEPARATOR, $packageName));

        return file_exists($localPath) ? $localPath : $vendorPath;
    }

    public function updatePackage($packageName, $version = null): string
    {
        $packagePath = $this->getPackagePath($packageName);

        if ($this->isDevMode() && file_exists($packagePath . '/.git')) {
            return $this->updatePackageInDevMode($packagePath);
        }

        return $this->updatePackageViaComposer($packageName, $version);
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

    private function updatePackageInDevMode($packagePath): string
    {
        try {
            $process = new Process(['git', 'pull'], $packagePath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            Log::info("Package updated successfully in dev mode. Output: " . $process->getOutput());
            return "Package updated successfully in dev mode.";
        } catch (\Exception $e) {
            Log::error("Failed to update package in dev mode. Error: " . $e->getMessage());
            throw new \Exception("Failed to update package in dev mode. Error: " . $e->getMessage());
        }
    }

    private function updatePackageViaComposer($packageName, $version = null): string
    {
        try {
            $command = ['require', "{$packageName}:{$version}", '--update-with-dependencies'];
            $process = $this->runComposerCommand($command);
            
            Log::info("Package {$packageName} updated successfully. Output: " . $process->getOutput());
            return "Package {$packageName} updated successfully.";
        } catch (\Exception $e) {
            Log::error("Failed to update package {$packageName}. Error: " . $e->getMessage());
            throw new \Exception("Failed to update package {$packageName}. Error: " . $e->getMessage());
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
        if (!is_writable($path)) {
            // 임시 디렉토리 사용
            $tempPath = sys_get_temp_dir() . '/' . basename($path);
            if (@copy($path, $tempPath)) {
                return $tempPath;
            }
            
            // 복사도 실패한 경우
            throw new \Exception("Unable to set write permissions on: $path. Please contact your server administrator to grant necessary permissions.");
        }
        return $path;
    }
}