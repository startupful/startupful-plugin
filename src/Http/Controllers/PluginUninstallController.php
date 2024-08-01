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

            // Remove plugin using Composer
            $result = $this->composerOperations->removePlugin($packageName);
            Log::info($result);

            // Remove from AdminPanelProvider
            $this->removePluginFromAdminPanelProvider($plugin);

            // Clear caches
            $this->clearCaches();

            // Remove from database
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