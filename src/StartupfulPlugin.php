<?php

namespace Startupful\StartupfulPlugin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Pages\InstallPlugin;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationGroup;

class StartupfulPlugin implements Plugin
{
    public function getId(): string
    {
        return 'startupful-plugin';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ManagePlugins::class,
                InstallPlugin::class,
            ])
            ->navigationGroups(['Startupful Plugin']);
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