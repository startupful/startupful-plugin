<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Illuminate\Support\Collection;
use Startupful\StartupfulPlugin\Http\Controllers\PluginInstallController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Startupful\StartupfulPlugin\Models\PluginSetting;

class InstallPluginPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'startupful::pages.install-plugin';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'startupful-install-plugin';

    public ?string $search = '';
    public Collection $plugins;
    public ?string $installationStatus = null;
    public bool $isSubscribed = false;

    public function mount(): void
    {
        $this->plugins = collect(StartupfulPlugin::getGithubRepo()->getStartupfulPlugins());
        $this->refreshPlugins();
        $this->checkSubscription();
    }

    private function checkSubscription(): void
    {
        $this->isSubscribed = $this->isVerified();
    }

    private function isVerified(): bool
    {
        return PluginSetting::where('plugin_id', 1)
            ->where('key', 'plugin-key')
            ->exists();
    }

    public static function getNavigationLabel(): string
    {
        return __('startupful-plugin.plugin_installation');
    }

    public function getTitle(): string
    {
        return __('startupful-plugin.plugin_installation');
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
        if (!$this->isSubscribed) {
            Notification::make()
                ->title("Subscription required")
                ->body("Please verify your subscription in the General Settings page before installing plugins.")
                ->warning()
                ->send();
            return;
        }

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
            Log::info("Plugin {$plugin['name']} installed status: " . ($plugin['installed'] ? 'true' : 'false'));
            return $plugin;
        });
    }

    private function getInstalledPlugins(): Collection
    {
        $plugins = DB::table('plugins')->select('name')->get();
        return $plugins;
    }
}