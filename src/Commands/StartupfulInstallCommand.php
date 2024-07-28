<?php

namespace Startupful\StartupfulPlugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StartupfulInstallCommand extends Command
{
    public $signature = 'startupful-plugin:install';

    public $description = 'Install the Startupful Plugin';

    public function handle()
    {
        parent::handle();

        $this->info('Installing Startupful Plugin...');

        $this->updateAdminPanelProvider();

        $this->info('Startupful Plugin has been installed successfully!');

        return Command::SUCCESS;
    }

    protected function updateAdminPanelProvider(): void
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');

        if (file_exists($providerPath)) {
            $content = file_get_contents($providerPath);

            // Add use statement if not exists
            if (!Str::contains($content, 'use Startupful\StartupfulPlugin\StartupfulPlugin;')) {
                $content = Str::replace(
                    "namespace App\Providers\Filament;",
                    "namespace App\Providers\Filament;\n\nuse Startupful\StartupfulPlugin\StartupfulPlugin;",
                    $content
                );
            }

            // Add plugin to panel if not exists
            if (!Str::contains($content, '->plugin(StartupfulPlugin::make())')) {
                $content = Str::replace(
                    "return \$panel",
                    "return \$panel\n            ->plugin(StartupfulPlugin::make())",
                    $content
                );
            }

            file_put_contents($providerPath, $content);

            $this->info('AdminPanelProvider.php updated successfully.');
        } else {
            $this->warn('AdminPanelProvider.php not found. Please add the plugin manually.');
        }
    }
}