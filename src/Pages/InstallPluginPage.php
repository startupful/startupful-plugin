<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Illuminate\Support\Collection;
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;
use Illuminate\Support\Facades\DB;

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

    public function installPlugin(string $pluginName): void
    {
        $plugin = $this->plugins->firstWhere('name', $pluginName);
        if (!$plugin) {
            $this->installationStatus = "Plugin not found: $pluginName";
            return;
        }

        $installController = StartupfulPlugin::getInstallController();
        $installController->installPlugin($plugin);
        $this->installationStatus = $installController->getInstallationStatus()[$pluginName] ?? null;
        
        $this->refreshPlugins();
    }

    public function uninstallPlugin(string $pluginName): void
    {
        $installController = StartupfulPlugin::getInstallController();
        $installController->uninstallPlugin($pluginName);
        $this->installationStatus = $installController->getUninstallationStatus()[$pluginName] ?? null;
        
        $this->refreshPlugins();
    }

    private function refreshPlugins(): void
    {
        $plugins = StartupfulPlugin::getGithubRepo()->getStartupfulPlugins();
        $this->plugins = $this->formatPlugins($plugins);
    }

    private function formatPlugins(array $plugins): Collection
    {
        $installedPlugins = $this->getInstalledPlugins();

        return collect($plugins)->map(function ($plugin) use ($installedPlugins) {
            $plugin['installed'] = $installedPlugins->contains('name', $plugin['name']);
            return $plugin;
        });
    }

    private function getInstalledPlugins(): Collection
    {
        return DB::table('plugins')->select('name')->get();
    }
}