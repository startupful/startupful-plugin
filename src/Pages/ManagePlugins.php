<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Startupful\StartupfulPlugin\Models\Plugin;

class ManagePlugins extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string $view = 'startupful::pages.manage-plugins';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'startupful-manage-plugins';

    public static function getNavigationLabel(): string
    {
        return 'Manage Plugins';
    }

    public function getInstalledPlugins()
    {
        return Plugin::all();
    }
}