<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Startupful\StartupfulPlugin\Models\Plugin;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Composer\Autoload\ClassLoader;

class InstallPlugin extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'startupful::pages.install-plugin';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'startupful-install-plugin';

    public static function getNavigationLabel(): string
    {
        return 'Install New Plugin';
    }

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
                    ->reactive()
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
        $plugin = is_string($plugin) ? json_decode($plugin, true) : $plugin;

        if (!is_array($plugin)) {
            $this->installationStatus = "Error: Invalid plugin data";
            Notification::make()
                ->title("Failed to install plugin: Invalid data format")
                ->danger()
                ->send();
            return;
        }

        $this->installationStatus = "Installing {$plugin['name']}...";

        try {
            $version = $plugin['latest_version'] ?? 'dev-master';
            
            // package_name을 developer로 사용
            $developer = $plugin['package_name'] ?? $plugin['full_name'] ?? '';
            
            $command = ['composer', 'require', '--no-cache', "{$developer}"];
            
            $env = getenv();
            $env['HOME'] = base_path();
            $env['COMPOSER_HOME'] = sys_get_temp_dir() . '/.composer';

            $process = new Process($command, base_path(), $env);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // developer 정보 추가
            $plugin['developer'] = $developer;

            $this->runDefaultInstallationSteps($plugin);

            $this->addToInstalledPlugins($plugin);

            $this->installationStatus = "Plugin {$plugin['name']} installed successfully.";
            Notification::make()
                ->title("Plugin '{$plugin['name']}' installed successfully.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->installationStatus = "Failed to install {$plugin['name']}. Error: " . $e->getMessage();
            Notification::make()
                ->title("Failed to install plugin")
                ->body("Error: " . $e->getMessage() . "\nPlease check the logs for more details.")
                ->danger()
                ->send();
        }
    }

    private function copyMigrationFiles($developer, $name): void
    {
        $possibleMigrationDirectories = [
            base_path("vendor/{$developer}/database/migrations"),
            base_path("vendor/{$developer}/src/database/migrations"),
            base_path("vendor/{$developer}/migrations"),
        ];

        $migrationFiles = [];
        foreach ($possibleMigrationDirectories as $directory) {
            $files = glob($directory . '/*.php');
            if (!empty($files)) {
                $migrationFiles = $files;
                break;
            }
        }

        \Log::info("Checking migration files in directories: " . implode(', ', $possibleMigrationDirectories));

        $copiedFiles = [];

        if (!empty($migrationFiles)) {
            foreach ($migrationFiles as $sourceMigrationPath) {
                $originalFileName = basename($sourceMigrationPath);
                $existingFiles = glob(database_path('migrations/*_' . $originalFileName));
                
                if (!empty($existingFiles)) {
                    \Log::info("Migration file already exists, skipping: {$originalFileName}");
                    continue;
                }

                $newTimestamp = date('Y_m_d_His');
                $newFileName = $newTimestamp . '_' . $originalFileName;
                $destinationMigrationPath = database_path('migrations/' . $newFileName);
                
                \Log::info("Copying migration file from: {$sourceMigrationPath} to: {$destinationMigrationPath}");
                
                if (copy($sourceMigrationPath, $destinationMigrationPath)) {
                    \Log::info("Migration file copied successfully with updated timestamp");
                    $copiedFiles[] = $destinationMigrationPath;
                } else {
                    throw new \Exception("Failed to copy migration file from {$sourceMigrationPath} to {$destinationMigrationPath}");
                }
            }
        } else {
            \Log::warning("No migration files found in any of the possible directories");
        }

        \Log::info("Copied migration files:", $copiedFiles);

        if (empty($copiedFiles)) {
            \Log::warning("No new migration files were copied");
        }
    }

    protected function addToInstalledPlugins($plugin): void
    {
        Plugin::create([
            'name' => $plugin['name'],
            'version' => $plugin['latest_version'] ?? 'unknown',
            'description' => $plugin['description'] ?? '',
            'developer' => $plugin['full_name'] ?? '',
            'is_active' => true,
            'installed_at' => now(),
        ]);
    }

    protected function runDefaultInstallationSteps($plugin): void
    {
        $name = $plugin['name'] ?? '';
        $developer = $plugin['developer'] ?? '';
        $className = $this->generateClassName($name);
        
        \Log::info("Starting installation steps for {$name}", ['developer' => $developer, 'class_name' => $className]);

        try {
            $possibleProviderClasses = [
                "Startupful\\{$className}\\{$className}ServiceProvider",
                "Startupful\\{$className}\\ServiceProvider",
                "Startupful\\".ucfirst($name)."\\ServiceProvider",
            ];
            
            \Log::info("Looking for service provider. Possible classes: " . implode(', ', $possibleProviderClasses));

            $providerClass = $this->findServiceProvider($possibleProviderClasses);

            if (!$providerClass) {
                throw new \Exception("Service provider class not found");
            }

            \Log::info("Service provider found: {$providerClass}");

            $this->copyMigrationFiles($developer, $name);
            $this->runMigrations($name);
            $this->updateAdminPanelProvider($className);

            $this->installationStatus = "Completed installation steps for {$name}";
            \Log::info("Installation completed for {$name}");

        } catch (\Exception $e) {
            $this->handleInstallationError($name, $e);
        }
    }

    private function generateClassName($name): string
    {
        return str_replace(['-', ' '], '', ucwords($name, '- '));
    }

    private function findServiceProvider($possibleClasses)
    {
        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        return null;
    }

    private function runMigrations($name): void
    {
        \Log::info("Running migrations for {$name}");
        $output = Artisan::call('migrate', ['--force' => true]);
        \Log::info("Migration command output: " . Artisan::output());

        if ($output !== 0) {
            throw new \Exception("Migration failed for {$name}. Output: " . Artisan::output());
        }

        \Log::info("Migrations completed for {$name}");
    }

    private function updateAdminPanelProvider($className): void
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        \Log::info("Updating AdminPanelProvider.php at: {$providerPath}");

        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php not found at: {$providerPath}");
        }

        $content = file_get_contents($providerPath);

        $useStatement = "use Startupful\\{$className}\\{$className}Plugin;";
        $pluginMethod = "->plugin({$className}Plugin::make())";

        if (!str_contains($content, $useStatement)) {
            $content = str_replace("namespace App\Providers\Filament;", "namespace App\Providers\Filament;\n\n{$useStatement}", $content);
            \Log::info("Use statement added: {$useStatement}");
        }

        if (!str_contains($content, $pluginMethod)) {
            $content = preg_replace('/(\->login\(\))/', "$1\n            {$pluginMethod}", $content);
            \Log::info("Plugin method added: {$pluginMethod}");
        }

        file_put_contents($providerPath, $content);
        \Log::info("AdminPanelProvider.php updated successfully");
    }

    private function handleInstallationError($name, \Exception $e): void
    {
        $this->installationStatus = "Installation failed for {$name}: " . $e->getMessage();
        \Log::error("Installation failed for {$name}: " . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}