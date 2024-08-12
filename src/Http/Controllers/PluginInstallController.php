<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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
            $migrationPath = "packages/startupful/{$plugin['name']}/database/migrations";
            Log::info("Migration path: {$migrationPath}");
            
            try {
                $output = '';
                Artisan::call('migrate', ['--path' => $migrationPath, '--force' => true], $output);
                Log::info("Migration output: " . $output);
                
                // Verify if the migration was successful
                if (!Schema::hasTable('avatars')) {
                    throw new \Exception("Migration failed: 'avatars' table not created");
                }
            } catch (\Exception $migrationException) {
                Log::error("Migration failed: " . $migrationException->getMessage());
                Log::error("Migration stack trace: " . $migrationException->getTraceAsString());
                throw $migrationException;
            }

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
        
        // Check if the error is related to database
        if ($e instanceof \Illuminate\Database\QueryException) {
            Log::error("Database error details: " . $e->getSql());
            Log::error("Database error bindings: " . json_encode($e->getBindings()));
        }
        
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
}