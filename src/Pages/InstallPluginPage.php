<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Illuminate\Support\Collection;
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;

class InstallPluginPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'startupful::pages.install-plugin';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'startupful-install-plugin';

    public ?string $search = '';
    public Collection $plugins;
    public ?string $installationStatus = null;

    public function mount(): void
    {
        $this->plugins = collect(StartupfulPlugin::getGithubRepo()->getStartupfulPlugins());
    }

    public static function getNavigationLabel(): string
    {
        return 'Install New Plugin';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('search')
                    ->placeholder('Search for Startupful plugins...')
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->searchPlugins()),
            ]);
    }

    public function searchPlugins(): void
    {
        $results = StartupfulPlugin::getGithubRepo()->searchPlugins($this->search);
        $this->plugins = collect($results);
    }

    public function installPlugin($plugin): void
    {
        $installController = StartupfulPlugin::getInstallController();
        $installController->installPlugin($plugin);
        $this->installationStatus = $installController->getInstallationStatus()[$plugin['name']] ?? null;
    }
}