<?php

use Illuminate\Support\Facades\Route;
use Filament\Startupful\Pages\ManagePlugins;
use Filament\Startupful\Pages\InstallPlugin;

Route::middleware([
    'web',
    'auth',
])->group(function () {
    Route::get('/admin/pages/startupful-manage-plugins', ManagePlugins::class)->name('filament.admin.pages.startupful-manage-plugins');
    Route::get('/admin/pages/startupful-install-plugin', InstallPlugin::class)->name('filament.admin.pages.startupful-install-plugin');
});