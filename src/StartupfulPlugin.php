<?php

namespace Filament\Startupful;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Startupful\Pages\ManagePlugins;
use Filament\Startupful\Pages\InstallPlugin;
use Filament\Startupful\Pages\BrowsePluginsPage;
use Filament\Startupful\Services\GithubPluginRepository;

class StartupfulPlugin implements Plugin
{
    public function getId(): string
    {
        return 'startupful';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ManagePlugins::class,
                InstallPlugin::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function getGithubRepo(): GithubPluginRepository
    {
        return app(GithubPluginRepository::class);
    }
}