<?php

namespace Startupful\StartupfulPlugin;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Startupful\StartupfulPlugin\Pages\GeneralSettings;
use Startupful\StartupfulPlugin\Pages\ManagePlugins;
use Startupful\StartupfulPlugin\Pages\InstallPluginPage;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;
use Illuminate\Support\Facades\Auth;

class StartupfulPlugin implements Plugin
{
    public function getId(): string
    {
        return 'startupful-plugin';
    }

    public function register(Panel $panel): void
    {
        if (Auth::check() && Auth::id() === 1) {
            try {
                $panel->pages([
                    GeneralSettings::class,
                    ManagePlugins::class,
                    InstallPluginPage::class
                ]);
            } catch (\Exception $e) {
                // Log the exception or handle it as needed
            }
        }
    }

    public function boot(Panel $panel): void
    {
        FilamentColor::register([
            'primary' => [
                50 => '238, 242, 255',   // Indigo 50
                100 => '224, 231, 255',  // Indigo 100
                200 => '199, 210, 254',  // Indigo 200
                300 => '165, 180, 252',  // Indigo 300
                400 => '129, 140, 248',  // Indigo 400
                500 => '99, 102, 241',   // Indigo 500
                600 => '79, 70, 229',    // Indigo 600
                700 => '67, 56, 202',    // Indigo 700
                800 => '55, 48, 163',    // Indigo 800
                900 => '49, 46, 129',    // Indigo 900
                950 => '30, 27, 75',     // Indigo 950
            ],
        ]);

        Filament::registerNavigationGroups([
            'Startupful Plugin',
            'Webpage Manager',
            'AI',
        ]);
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