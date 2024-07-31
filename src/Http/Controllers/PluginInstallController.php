<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Startupful\StartupfulPlugin\Models\Plugin;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

class PluginInstallController
{
    public $installationStatus;
    protected $plugin;

    public function installPlugin($plugin): void
    {
        if (is_string($plugin)) {
            $plugin = json_decode($plugin, true);
        }

        if (!is_array($plugin)) {
            $this->installationStatus = "Error: Invalid plugin data";
            Notification::make()
                ->title("Failed to install plugin: Invalid data format")
                ->danger()
                ->send();
            return;
        }

        $this->plugin = $plugin;

        $this->installationStatus = "Installing {$plugin['name']}...";

        try {
            $version = $plugin['latest_version'] ?? 'dev-master';
            $developer = $plugin['package_name'] ?? $plugin['full_name'] ?? '';
            
            $this->runComposerRequire($developer);
            
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
            $this->handleInstallationError($plugin['name'], $e);
        }
    }

    protected function runDefaultInstallationSteps($plugin): void
    {
        $name = $plugin['name'] ?? '';
        $developer = $plugin['developer'] ?? '';
        
        \Log::info("Starting installation steps for {$name}", ['developer' => $developer]);

        try {
            $providerClass = $this->generateServiceProviderClass($name);

            \Log::info("Generated service provider class: {$providerClass}");

            // 나머지 설치 단계 진행
            $this->copyMigrationFiles($developer, $name);
            $this->runMigrations($name);
            $this->updateAdminPanelProvider($providerClass);

            $this->installationStatus = "Completed installation steps for {$name}";
            \Log::info("Installation completed for {$name}");

        } catch (\Exception $e) {
            $this->handleInstallationError($name, $e);
        }
    }

    protected function generateServiceProviderClass($name): string
    {
        // 특수기호 제거 및 첫 알파벳과 특수기호 앞의 텍스트를 대문자로 변환
        $name_2 = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        return "Startupful\\{$name_2}\\{$name_2}Plugin";
    }

    protected function runComposerRequire(string $packageName): void
    {
        $command = ['composer', 'require', '--no-cache', $packageName];
        $env = getenv();
        $env['HOME'] = base_path();
        $env['COMPOSER_HOME'] = sys_get_temp_dir() . '/.composer';

        $process = new Process($command, base_path(), $env);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
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
        \Log::info("Checking if class exists: {$className}");

        $shortClassName = $this->getShortClassName($className);

        if (class_exists($className)) {
            \Log::info("Class exists: {$className}. No need to update AdminPanelProvider.php");
            return;
        }

        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        \Log::info("Class does not exist. Updating AdminPanelProvider.php at: {$providerPath}");

        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php not found at: {$providerPath}");
        }

        $content = file_get_contents($providerPath);

        $useStatement = "use {$className};";
        $pluginMethod = "->plugin({$shortClassName}::make())";

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

    private function getShortClassName($className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    protected function addToInstalledPlugins(array $plugin): void
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

    private function handleInstallationError($name, \Exception $e): void
    {
        $this->installationStatus = "Installation failed for {$name}: " . $e->getMessage();
        \Log::error("Installation failed for {$name}: " . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
        Notification::make()
            ->title("Failed to install plugin")
            ->body("Error: " . $e->getMessage() . "\nPlease check the logs for more details.")
            ->danger()
            ->send();
    }

    protected function generateClassName(string $name): string
    {
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }
}