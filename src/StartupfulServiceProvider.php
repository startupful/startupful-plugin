<?php

namespace Startupful\StartupfulPlugin;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Filament\Facades\Filament;
use Startupful\StartupfulPlugin\Pages\InstallPlugin;
use Startupful\StartupfulPlugin\Pages\InstalledPlugins;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Commands\StartupfulInstallCommand;

class StartupfulServiceProvider extends PackageServiceProvider
{
    public static string $name = 'startupful';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews('startupful')
            ->hasCommand(StartupfulInstallCommand::class);
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $this->app->singleton(GithubPluginRepository::class, function ($app) {
            return new GithubPluginRepository();
        });
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->scoped(StartupfulPlugin::class, fn () => StartupfulPlugin::make());
    }
}