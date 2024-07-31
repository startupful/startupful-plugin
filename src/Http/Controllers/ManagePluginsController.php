<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\App;
use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Startupful\StartupfulPlugin\StartupfulPlugin;

class ManagePluginsController
{
    use Tables\Concerns\InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Plugin::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('developer'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('installed_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Plugin $record) => $this->updatePlugin($record))
                    ->disabled(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version;
                    })
                    ->tooltip(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version
                            ? 'Plugin is up to date'
                            : 'Update available';
                    })
                    ->color(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version
                            ? 'gray'
                            : 'primary';
                    }),
                DeleteAction::make()
                    ->action(function (Plugin $record) {
                        $this->uninstallPlugin($record);
                    })
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function ($records) {
                        foreach ($records as $record) {
                            $this->uninstallPlugin($record);
                        }
                    }),
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
        try {
            $this->getUpdateController()->updatePlugin($plugin);
        } catch (\Exception $e) {
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