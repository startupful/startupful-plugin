<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

class PluginInstallController
{
    protected $composerOperations;
    protected $installationStatus = [];

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
            Log::info("Starting migrations for plugin: {$plugin['name']}");
            $output = '';

            if ($plugin['name'] !== 'startupful-plugin') {
                $migrationPath = "vendor/startupful/{$plugin['name']}/database/migrations";
                Log::info("Running migrations from path: {$migrationPath}");
                Artisan::call('migrate', ['--path' => $migrationPath, '--force' => true], $output);
            } else {
                Log::info("Running all migrations");
                Artisan::call('migrate', ['--force' => true], $output);
            }

            Log::info("Migration output: " . $output);

            // Verify if the migration was successful (assuming 'avatars' table should be created)
            if (!Schema::hasTable('avatars')) {
                Log::error("Migration failed: 'avatars' table not created");
                throw new \Exception("Migration failed: 'avatars' table not created");
            }

            $this->publishAssets($plugin);

            // Update AdminPanelProvider
            $this->updateAdminPanelProvider($plugin);

            // Add to installed plugins
            $this->addToInstalledPlugins($plugin);

            $this->installationStatus[$plugin['name']] = 'success';

            Notification::make()
                ->title("Plugin '{$plugin['name']}' installed successfully.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->installationStatus[$plugin['name']] = 'failed';
            Log::error("Detailed installation error for plugin {$plugin['name']}: " . $e->getMessage());
            Log::error("Installation stack trace: " . $e->getTraceAsString());
            $this->handleInstallationError($plugin['name'], $e);
        }
    }

    private function updateAdminPanelProvider($plugin): void
    {
        $className = "Startupful\\{$this->generateClassName($plugin['name'])}\\{$this->generateClassName($plugin['name'])}Plugin";
        $shortClassName = $this->getShortClassName($className);
        
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

    private function getShortClassName($className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function generateClassName(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    public function getInstallationStatus(): array
    {
        return $this->installationStatus;
    }

    private function publishAssets($plugin): void
    {
        Log::info("Publishing assets for plugin: {$plugin['name']}");
        
        try {
            // Publish internal assets
            $this->publishInternalAssets($plugin);

            // Publish external assets
            $this->publishExternalAssets($plugin);
        } catch (\Exception $e) {
            Log::error("Error publishing assets for plugin {$plugin['name']}: " . $e->getMessage());
            throw $e;
        }
    }

    private function publishExternalAssets($plugin): void
    {
        $configKey = str_replace('-', '_', $plugin['name']);
        $config = Config::get($configKey, []);
        $externalAssets = $config['external_assets'] ?? [];

        foreach ($externalAssets as $externalAsset) {
            if (isset($externalAsset['provider'])) {
                $output = '';
                $command = [
                    '--provider' => $externalAsset['provider'],
                    '--force' => true
                ];

                if (isset($externalAsset['tag'])) {
                    $command['--tag'] = $externalAsset['tag'];
                }

                Artisan::call('vendor:publish', $command, $output);
                Log::info("External asset publishing output for {$plugin['name']} (Provider: {$externalAsset['provider']}): " . $output);
            }
        }

        // Directly publish Laraberg assets
        if ($plugin['name'] === 'webpage-manager') {
            $output = '';
            Artisan::call('vendor:publish', [
                '--provider' => 'Startupful\WebpageManager\WebpageManagerServiceProvider',
                '--tag' => 'laraberg-assets',
                '--force' => true
            ], $output);
            Log::info("Laraberg asset publishing output: " . $output);
        }
    }
}