<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Filament\Forms;
use Filament\Tables;
use Livewire\Component;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Support\Facades\App;
use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\Models\PluginSetting;
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

    protected function isVerified(): bool
    {
        return PluginSetting::where('plugin_id', 1)
            ->where('key', 'plugin-key')
            ->exists();
    }

    public function table(Table $table): Table
    {
        if (!$this->isVerified()) {
            return $table
                ->query(Plugin::query())
                ->emptyStateHeading('Subscription Required')
                ->emptyStateDescription('Please verify your subscription in the General Settings page to manage plugins.');
        }

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
                Action::make('update')
                    ->label(__('startupful-plugin.update'))
                    ->action(fn (Plugin $record) => $this->updatePlugin($record))
                    ->requiresConfirmation()
                    ->hidden(fn (Plugin $record) => $record->name === 'startupful-plugin' && $this->getLatestStartupfulPluginVersion() <= $record->version),
                Action::make('uninstall')
                    ->label(__('startupful-plugin.uninstall'))
                    ->action(fn (Plugin $record) => $this->uninstallPlugin($record))
                    ->requiresConfirmation()
                    ->hidden(fn (Plugin $record) => $record->is_core),
            ])
            ->headerActions([
            ]);
    }

    public function getInstalledPlugins()
    {
        if (!$this->isVerified()) {
            return collect();
        }
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
        if (!$this->isVerified()) {
            Notification::make()
                ->title("Subscription verification required")
                ->body("Please verify your subscription in the General Settings page before updating plugins.")
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
        if (!$this->isVerified()) {
            Notification::make()
                ->title("Subscription verification required")
                ->body("Please verify your subscription in the General Settings page before uninstalling plugins.")
                ->warning()
                ->send();
            return;
        }

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