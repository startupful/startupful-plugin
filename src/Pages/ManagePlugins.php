<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Startupful\StartupfulPlugin\Models\Plugin;

class ManagePlugins extends Page
{
    protected static string $view = 'startupful::pages.manage-plugins';

    protected static ?string $navigationLabel = 'Installed Plugins';

    protected static ?string $slug = 'startupful-manage-plugins';

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getInstalledPlugins()
    {
        return Plugin::all();
    }
}