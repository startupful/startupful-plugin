<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class PluginUpdateController
{
    protected $composerOperations;

    public function __construct(ComposerOperationsController $composerOperations)
    {
        $this->composerOperations = $composerOperations;
    }

    public function updatePlugin(Plugin $plugin): void
    {
        try {
            $latestVersion = $this->getLatestVersion($plugin);

            Log::info("Starting update process for plugin: {$plugin->name}");

            // Update the plugin using Composer
            $result = $this->composerOperations->updatePlugin($plugin->developer);
            Log::info($result);

            // Run migrations
            Artisan::call('migrate');

            // Update plugin record in database
            $this->updatePluginRecord($plugin, $latestVersion);

            // Clear caches
            $this->clearCaches();

            Notification::make()
                ->title("Plugin '{$plugin->name}' updated successfully to version {$latestVersion}.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            $this->handleUpdateError($plugin->name, $e);
        }
    }

    private function getLatestVersion(Plugin $plugin): string
    {
        try {
            $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($plugin->developer);
            return $latestVersion ?? $plugin->version;
        } catch (\Exception $e) {
            Log::error('Failed to get latest version: ' . $e->getMessage());
            return $plugin->version;
        }
    }

    private function updatePluginRecord(Plugin $plugin, string $latestVersion): void
    {
        $plugin->update([
            'version' => $latestVersion,
            'last_updated_at' => now(),
        ]);
        Log::info("Updated plugin record for {$plugin->name} to version {$latestVersion}");
    }

    private function clearCaches(): void
    {
        Artisan::call('optimize:clear');
    }

    private function handleUpdateError(string $name, \Exception $e): void
    {
        $errorMessage = "Update failed for {$name}: " . $e->getMessage();
        Log::error($errorMessage, [
            'exception' => $e,
            'trace' => $e->getTraceAsString(),
            'plugin_name' => $name
        ]);

        Notification::make()
            ->title("Failed to update plugin")
            ->body($errorMessage)
            ->danger()
            ->send();
    }
}