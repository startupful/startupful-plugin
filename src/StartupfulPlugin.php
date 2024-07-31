<?php

namespace Startupful\StartupfulPlugin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Pages\InstallPluginPage;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;

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
                InstallPluginPage::class
            ]);
        } catch (\Exception $e) {
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

    public static function getInstallController(): PluginInstallController
    {
        return app(PluginInstallController::class);
    }
}