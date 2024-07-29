<?php

namespace Startupful\StartupfulPlugin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Log;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Pages\InstallPlugin;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;

class StartupfulPlugin implements Plugin
{
    public function getId(): string
    {
        return 'startupful-plugin';
    }

    public function register(Panel $panel): void
    {
        try {
            $panel->pages([
                ManagePlugins::class,
                InstallPlugin::class
            ]);
        } catch (\Exception $e) {
            Log::error('Error in StartupfulPlugin register method: ' . $e->getMessage());
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }

    public static function getGithubRepo(): GithubPluginRepository
    {
        return app(GithubPluginRepository::class);
    }
}