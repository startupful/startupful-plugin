<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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

            // Update the package using Composer
            $result = $this->composerOperations->updatePackage($plugin->developer, $latestVersion);
            Log::info($result);

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
        $this->composerOperations->clearComposerCache();
        $this->composerOperations->dumpAutoload();
        // Clear Laravel caches
        \Artisan::call('config:clear');
        \Artisan::call('cache:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
        \Artisan::call('optimize:clear');
    }

    protected function handleUpdateError(string $name, \Exception $e): void
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