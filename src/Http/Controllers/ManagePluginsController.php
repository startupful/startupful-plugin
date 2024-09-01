<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Filament\Forms;
use Filament\Tables;
use Livewire\Component;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Support\Facades\Session;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Support\Facades\App;
use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class ManagePluginsController
{
    use Tables\Concerns\InteractsWithTable;

    protected $mainServerUrl = 'https://startupful.io';
    protected $githubRepo;

    public function __construct(GithubPluginRepository $githubRepo)
    {
        $this->githubRepo = $githubRepo;
    }

    protected function getGithubRepo(): GithubPluginRepository
    {
        return App::make(GithubPluginRepository::class);
    }

    protected function getLatestStartupfulPluginVersion(): ?string
    {
        return $this->githubRepo->getLatestVersion('startupful/startupful-plugin');
    }

    protected function getCurrentStartupfulPluginVersion(): string
    {
        $startupfulPlugin = Plugin::where('name', 'startupful-plugin')->first();
        return $startupfulPlugin ? $startupfulPlugin->version : 'Unknown';
    }

    public function table(Table $table): Table
    {

        return $table
            ->query(Plugin::query()->orderByDesc('is_core')->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('startupful-plugin.plugin_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->label(__('startupful-plugin.version'))
                    ->formatStateUsing(function (Plugin $record) {
                        if ($record->name === 'startupful-plugin') {
                            $latestVersion = $this->getLatestStartupfulPluginVersion();
                            return $record->version . ($latestVersion && version_compare($latestVersion, $record->version, '>') ? " (Update available: $latestVersion)" : '');
                        }
                        return $record->version;
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('startupful-plugin.description'))
                    ->limit(100),
                Tables\Columns\TextColumn::make('installed_at')
                    ->label(__('startupful-plugin.install_date'))
                    ->dateTime(),
            ])
            ->searchable(false)
            ->actions([
            ])
            ->headerActions([
            ]);
    }

    public function getInstalledPlugins()
    {
        return Plugin::all();
    }

    protected function getUpdateController()
    {
        return App::make(PluginUpdateController::class);
    }

    protected function getUninstallController()
    {
        return App::make(PluginUninstallController::class);
    }

    public function updatePlugin(Plugin $plugin): void
    {
        if (!Session::get('is_verified', false)) {
            Notification::make()
                ->title("Subscription verification required")
                ->body("Please verify your subscription before updating plugins.")
                ->warning()
                ->send();
            return;
        }

        try {
            $this->getUpdateController()->updatePlugin($plugin);
        } catch (\Exception $e) {
            Log::error('Plugin update failed', [
                'plugin' => $plugin->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Notification::make()
                ->title("Failed to update plugin")
                ->body("Error: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function uninstallPlugin($plugin): void
    {
        try {
            $this->getUninstallController()->uninstallPlugin($plugin);
        } catch (\Exception $e) {
            Notification::make()
                ->title("Failed to uninstall plugin")
                ->body("Error: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}