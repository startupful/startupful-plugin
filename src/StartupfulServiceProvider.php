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
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;
use Startupful\StartupfulPlugin\Models\Plugin;

class StartupfulServiceProvider extends PackageServiceProvider
{
    public static string $name = 'startupful';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews('startupful')
            ->hasMigrations([
                'create_plugins_table',
                'create_plugin_settings_table'
            ])
            ->runsMigrations()
            ->hasCommand(StartupfulInstallCommand::class);
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->singleton(PluginFileManager::class, function ($app) {
            return new PluginFileManager();
        });

        $this->app->singleton(GithubPluginRepository::class, function ($app) {
            return new GithubPluginRepository();
        });

        $this->app->when(PluginInstallController::class)
                  ->needs(PluginFileManager::class)
                  ->give(function () {
                      return app(PluginFileManager::class);
                  });
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->scoped(StartupfulPlugin::class, fn () => StartupfulPlugin::make());
    }

    public function boot()
    {
        parent::boot();
        
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'startupful-plugin');
    }
}