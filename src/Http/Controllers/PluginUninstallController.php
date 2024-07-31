<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\Http\Controllers\ComposerOperationsController;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class PluginUninstallController
{
    protected $composerOperations;

    public function __construct(ComposerOperationsController $composerOperations)
    {
        $this->composerOperations = $composerOperations;
    }

    public function uninstallPlugin($plugin): void
    {
        if (!$plugin instanceof Plugin) {
            $plugin = Plugin::findOrFail($plugin);
        }
        
        try {
            $packageName = $plugin->developer;

            // AdminPanelProvider에서 플러그인 제거
            $this->removePluginFromAdminPanelProvider($plugin);

            // Composer를 통해 패키지 제거
            Log::info("Attempting to remove package via Composer: " . $packageName);
            $result = $this->composerOperations->removePackage($packageName);
            Log::info("Composer remove command result: " . $result);

            // 파일 정리
            $this->cleanupPluginFiles($packageName);

            // 캐시 정리 및 autoload 갱신
            $this->composerOperations->clearComposerCache();
            $this->composerOperations->dumpAutoload();
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('optimize:clear');

            // 데이터베이스에서 플러그인 제거
            $this->deletePluginFromDatabase($plugin);

            Notification::make()
                ->title("Plugin '{$plugin->name}' uninstalled successfully.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error("Error uninstalling plugin: " . $e->getMessage());
            throw $e;
        }
    }

    protected function cleanupPluginFiles($packageName): void
    {   
        $vendorDir = base_path('vendor/' . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        if (is_dir($vendorDir)) {
            try {
                $this->composerOperations->forceDeleteDirectory($vendorDir);
                Log::info("Successfully deleted vendor directory: " . $vendorDir);
            } catch (\Exception $e) {
                Log::warning("Failed to delete vendor directory: " . $e->getMessage());
                
                // 강제 삭제 시도
                try {
                    $command = "rm -rf " . escapeshellarg($vendorDir);
                    exec($command, $output, $returnVar);
                    
                    if ($returnVar === 0) {
                        Log::info("Successfully force deleted vendor directory: " . $vendorDir);
                    } else {
                        Log::error("Failed to force delete vendor directory: " . $vendorDir);
                        
                        // 추가적인 삭제 시도
                        $this->recursiveDelete($vendorDir);
                    }
                } catch (\Exception $e) {
                    Log::error("Error during force delete: " . $e->getMessage());
                }
            }
        }
    }

    private function recursiveDelete($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                        $this->recursiveDelete($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        @unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
            @rmdir($dir);
        }
    }

    protected function removePluginFromAdminPanelProvider(Plugin $plugin): void
    {
        $className = "Startupful\\{$this->generateClassName($plugin->name)}\\{$this->generateClassName($plugin->name)}Plugin";
        $shortClassName = $this->getShortClassName($className);

        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php not found at: {$providerPath}");
        }

        $content = file_get_contents($providerPath);

        $useStatement = "use {$className};";
        $pluginMethod = "->plugin({$shortClassName}::make())";

        // Remove use statement
        $content = str_replace($useStatement . "\n", '', $content);
        Log::info("Use statement removed: {$useStatement}");

        // Remove plugin method
        $pattern = '/\s*' . preg_quote($pluginMethod, '/') . '/';
        $content = preg_replace($pattern, '', $content);
        Log::info("Plugin method removed: {$pluginMethod}");

        file_put_contents($providerPath, $content);
        Log::info("AdminPanelProvider.php updated successfully");
    }

    private function generateClassName(string $name): string
    {
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    private function getShortClassName($className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    protected function deletePluginFromDatabase(Plugin $plugin): void
    {
        $deleteResult = $plugin->delete();

        if (!$deleteResult) {
            throw new \Exception("Failed to delete plugin from database");
        }
    }
}