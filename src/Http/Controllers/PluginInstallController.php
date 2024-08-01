<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PluginInstallController
{
    protected $composerOperations;

    public function __construct(ComposerOperationsController $composerOperations)
    {
        $this->composerOperations = $composerOperations;
    }

    public function installPlugin($plugin): void
    {
        if (is_string($plugin)) {
            $plugin = json_decode($plugin, true);
        }

        if (!is_array($plugin)) {
            Notification::make()
                ->title("Failed to install plugin: Invalid data format")
                ->danger()
                ->send();
            return;
        }

        try {
            $packageName = $plugin['package_name'] ?? $plugin['full_name'] ?? '';
            $version = $plugin['latest_version'] ?? '*';

            // Manually create directory and set permissions
            $path = base_path("vendor/" . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
            chmod($path, 0755);

            // Install plugin using Composer
            $result = $this->composerOperations->installPlugin($packageName, $version);
            Log::info($result);

            // Run migrations
            Artisan::call('migrate');

            // Update AdminPanelProvider
            $this->updateAdminPanelProvider($plugin);

            // Add to installed plugins
            $this->addToInstalledPlugins($plugin);

            Notification::make()
                ->title("Plugin '{$plugin['name']}' installed successfully.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error("Detailed installation error: " . $e->getMessage());
            $this->handleInstallationError($plugin['name'], $e);
        }
    }

    private function updateAdminPanelProvider($className): void
    {
        $shortClassName = $this->getShortClassName($className);
        $packageName = $this->plugin['package_name'] ?? $this->plugin['full_name'] ?? '';
        $pluginPath = $this->pluginFileManager->getPluginPath($packageName);
        
        $classFile = $pluginPath . '/src/' . $shortClassName . '.php';
        
        Log::info("Checking if class file exists: {$classFile}");

        if (!file_exists($classFile)) {
            Log::warning("Class file does not exist: {$classFile}. Skipping AdminPanelProvider update.");
            return;
        }

        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        Log::info("Updating AdminPanelProvider.php at: {$providerPath}");

        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php not found at: {$providerPath}");
        }

        $content = file_get_contents($providerPath);

        $useStatement = "use {$className};";
        $pluginMethod = "->plugin({$shortClassName}::make())";

        if (!str_contains($content, $useStatement)) {
            $content = str_replace("namespace App\Providers\Filament;", "namespace App\Providers\Filament;\n\n{$useStatement}", $content);
            Log::info("Use statement added: {$useStatement}");
        }

        if (!str_contains($content, $pluginMethod)) {
            // Look for the ->login() method and add the plugin method after it
            $pattern = '/(\->login\([^)]*\))/';
            if (preg_match($pattern, $content, $matches)) {
                $replacement = $matches[0] . "\n            {$pluginMethod}";
                $content = preg_replace($pattern, $replacement, $content, 1);
                Log::info("Plugin method added after ->login(): {$pluginMethod}");
            } else {
                Log::warning("Could not find ->login() method. Adding plugin method at the end of panel configuration.");
                // If ->login() is not found, add the plugin method at the end of the panel configuration
                $pattern = '/(return\s+\$panel\s*;)/';
                $replacement = "            {$pluginMethod}\n        $1";
                $content = preg_replace($pattern, $replacement, $content, 1);
            }
        } else {
            Log::info("Plugin method already exists: {$pluginMethod}");
        }

        file_put_contents($providerPath, $content);
        Log::info("AdminPanelProvider.php updated successfully");
    }

    private function addToInstalledPlugins(array $plugin): void
    {
        Plugin::create([
            'name' => $plugin['name'],
            'version' => $plugin['latest_version'] ?? 'unknown',
            'description' => $plugin['description'] ?? '',
            'developer' => $plugin['full_name'] ?? '',
            'is_active' => true,
            'installed_at' => now(),
        ]);
    }

    private function handleInstallationError($name, \Exception $e): void
    {
        $errorMessage = "Installation failed for {$name}: " . $e->getMessage();
        Log::error($errorMessage, [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
        Notification::make()
            ->title("Failed to install plugin")
            ->body("Error: " . $e->getMessage() . "\nPlease check the logs for more details.")
            ->danger()
            ->send();
    }
}