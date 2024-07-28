<?php

namespace Startupful\StartupfulPlugin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Pages\InstallPlugin;
use Startupful\StartupfulPlugin\Pages\BrowsePluginsPage;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Filament\Navigation\NavigationItem;

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
            ])
            ->navigationItems([
                NavigationItem::make('Manage Plugins')
                    ->icon('heroicon-o-rectangle-stack')
                    ->activeIcon('heroicon-s-rectangle-stack')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.startupful-manage-plugins'))
                    ->url(route('filament.admin.pages.startupful-manage-plugins')),
                NavigationItem::make('Install New Plugin')
                    ->icon('heroicon-o-plus-circle')
                    ->activeIcon('heroicon-s-plus-circle')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.startupful-install-plugin'))
                    ->url(route('filament.admin.pages.startupful-install-plugin')),
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