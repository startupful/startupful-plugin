<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Startupful\StartupfulPlugin\Http\Controllers\ManagePluginsController;
use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\Models\PluginSetting;
use Filament\Notifications\Notification;

class ManagePlugins extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'startupful-manage-plugins';
    protected static ?string $modelLabel = 'Plugin';

    protected static string $view = 'startupful::pages.manage-plugins';

    public static function getNavigationLabel(): string
    {
        return __('startupful-plugin.plugin_management');
    }

    public function getTitle(): string
    {
        return __('startupful-plugin.plugin_management');
    }

    public function table(Table $table): Table
    {
        return app(ManagePluginsController::class)->table($table);
    }

    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Plugin::query();
    }

    protected function getTableActionsPosition(): ?string
    {
        return Tables\Actions\Position::AfterCells;
    }

    public function getInstalledPlugins()
    {
        return app(ManagePluginsController::class)->getInstalledPlugins();
    }
}