<?php

namespace Filament\Startupful;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Filament\Startupful\Services\GithubPluginRepository;
use Filament\Facades\Filament;
use Filament\Startupful\Pages\InstallPlugin;
use Filament\Startupful\Pages\InstalledPlugins;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

class StartupfulServiceProvider extends PackageServiceProvider
{
    public static string $name = 'startupful';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews('startupful')
            ->hasMigrations(['create_plugins_table', 'create_plugin_settings_table'])
            ->runsMigrations()
            ->hasRoutes(['web'])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->endWith(function(InstallCommand $command) {
                        $command->info('Startupful plugin has been installed successfully!');
                    });
            });
    }

    public function packageBooted(): void
    {
        $this->app->singleton(GithubPluginRepository::class, function ($app) {
            return new GithubPluginRepository();
        });
    }
}