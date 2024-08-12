<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
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

            // Remove from AdminPanelProvider
            $this->removePluginFromAdminPanelProvider($plugin);

            // Remove plugin using Composer
            $result = $this->composerOperations->removePlugin($packageName);
            Log::info($result);

            // Clear caches
            $this->clearCaches();

            // Remove from database
            $this->deletePluginFromDatabase($plugin);

            Artisan::call('optimize:clear');

            $result = $this->composerOperations->dumpAutoload();

            Notification::make()
                ->title("Plugin '{$plugin->name}' uninstalled successfully.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error("Error uninstalling plugin: " . $e->getMessage());
            throw $e;
        }
    }

    protected function removePluginFromAdminPanelProvider(Plugin $plugin): void
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php not found at: {$providerPath}");
        }

        $content = file_get_contents($providerPath);

        // 플러그인 클래스 이름 생성
        $className = "Startupful\\{$this->generateClassName($plugin->name)}\\{$this->generateClassName($plugin->name)}Plugin";
        $shortClassName = $this->getShortClassName($className);

        // use 문 제거 (더 유연한 패턴 사용)
        $usePattern = '/use\s+Startupful\\\\.*' . preg_quote($shortClassName, '/') . '\s*;/';
        $content = preg_replace($usePattern, '', $content);

        // ->plugin() 메서드 호출 제거 (더 유연한 패턴 사용)
        $pluginPattern = '/\s*->plugin\(\s*' . preg_quote($shortClassName, '/') . '::make\(\)\s*\)/';
        $content = preg_replace($pluginPattern, '', $content);

        // 빈 줄 제거
        $content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $content);

        file_put_contents($providerPath, $content);
        Log::info("AdminPanelProvider.php updated successfully for plugin: {$plugin->name}");

        // 변경 사항 확인
        if (strpos($content, $shortClassName) !== false) {
            Log::warning("Plugin {$shortClassName} might still be present in AdminPanelProvider.php");
        }
    }

    private function clearCaches(): void
    {
        Artisan::call('optimize:clear');
    }

    private function deletePluginFromDatabase(Plugin $plugin): void
    {
        $plugin->delete();
    }

    private function generateClassName(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    private function getShortClassName($className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}