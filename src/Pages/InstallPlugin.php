<?php

namespace Filament\Startupful\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Startupful\Models\Plugin;
use Filament\Forms\Form;
use Filament\Startupful\StartupfulPlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class InstallPlugin extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'startupful::pages.install-plugin';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?string $navigationLabel = 'Install New Plugin';
    protected static ?string $slug = 'install-new-plugin';

    public ?string $search = '';
    public Collection $plugins;
    public ?string $installationStatus = null;

    public function mount(): void
    {
        $this->plugins = collect(StartupfulPlugin::getGithubRepo()->getStartupfulPlugins());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('search')
                    ->placeholder('Search for Startupful plugins...')
                    ->live()
                    ->debounce(500)
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
        $this->installationStatus = "Installing {$plugin['name']}...";

        try {
            $process = new Process(['composer', 'require', $plugin['package_name']]);
            $process->setWorkingDirectory(base_path());
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->installationStatus = "Plugin {$plugin['name']} installed successfully.";
            $this->notify('success', "Plugin '{$plugin['name']}' installed successfully.");
        } catch (\Exception $e) {
            $this->installationStatus = "Failed to install {$plugin['name']}.";
            $this->notify('error', "Failed to install plugin: " . $e->getMessage());
        }
    }

    protected function addToInstalledPlugins($plugin): void
    {
        Plugin::create([
            'name' => $plugin['name'],
            'version' => $this->getInstalledVersion($plugin['package_name']),
            'description' => $plugin['description'],
            'developer' => $plugin['full_name'],
            'is_active' => true,
            'installed_at' => now(),
        ]);
    }
}