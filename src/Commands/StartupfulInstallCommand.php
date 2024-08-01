<?php

namespace Startupful\StartupfulPlugin\Commands;

use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Startupful\StartupfulPlugin\Models\Plugin;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class StartupfulInstallCommand extends Command
{
    public $signature = 'startupful-plugin:install';

    public $description = 'Install the Startupful Plugin';

    public function handle()
    {
        $this->info('Installing Startupful Plugin...');

        // Publish migrations
        $this->call('vendor:publish', [
            '--provider' => 'Startupful\StartupfulPlugin\StartupfulServiceProvider',
            '--tag' => 'startupful-migrations'
        ]);

        // Run migrations
        $this->call('migrate');

        // Install Filament
        $this->installFilament();

        // Install Socialstream
        $this->installSocialstream();

        // Install OpenAI
        $this->installOpenAI();

        // Original Startupful Plugin installation process
        $this->installStartupfulPlugin();

        $this->info('All installations completed successfully.');
    }

    private function getCurrentVersion(): string
    {
        $composerJson = File::get(__DIR__ . '/../../composer.json');
        $composerData = json_decode($composerJson, true);
        return $composerData['version'] ?? '1.0.0';  // 기본값으로 1.0.0 사용
    }

    protected function publishMigrations(): void
    {
        $this->info('Publishing migrations...');

        $migrations = [
            'create_plugins_table.php.stub',
            'create_plugin_settings_table.php.stub'
        ];

        foreach ($migrations as $migration) {
            $sourcePath = __DIR__ . '/../../../database/migrations/' . $migration;
            $targetPath = database_path('migrations/' . date('Y_m_d_His_') . Str::before($migration, '.stub') . '.php');

            if (File::exists($sourcePath)) {
                if (!File::exists($targetPath)) {
                    File::copy($sourcePath, $targetPath);
                    $this->info("Published migration: " . basename($targetPath));
                } else {
                    $this->info("Migration already exists: " . basename($targetPath));
                }
            } else {
                $this->warn("Migration file not found: " . $migration);
                $this->warn("Looked in: " . $sourcePath);
            }
        }
    }

    protected function runMigrations(): void
    {
        $this->info('Running migrations...');

        $output = new BufferedOutput();
        $this->call('migrate', [
            '--path' => 'database/migrations',
            '--force' => true
        ], $output);

        $this->info($output->fetch());
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
                $content = preg_replace(
                    '/(->login\(\))/',
                    "$1\n            ->plugin(StartupfulPlugin::make())",
                    $content
                );
            }

            file_put_contents($providerPath, $content);

            $this->info('AdminPanelProvider.php updated successfully.');
        } else {
            $this->warn('AdminPanelProvider.php not found. Please add the plugin manually.');
        }
    }

    private function installFilament()
    {
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->info('Filament is already installed. Skipping...');
            return;
        }

        $this->info('Installing Filament...');
        $this->runComposerCommand('require filament/filament');

        $this->info('Creating Filament user...');
        $this->call('make:filament-user');
    }

    private function installSocialstream()
    {
        if (class_exists(\JoelButcher\Socialstream\SocialstreamServiceProvider::class)) {
            $this->info('Socialstream is already installed. Skipping...');
            return;
        }

        $this->info('Installing Socialstream...');
        $this->runComposerCommand('require joelbutcher/socialstream -W');

        $this->info('Running Socialstream installation...');

        // 사용자 입력을 시뮬레이션하여 Socialstream 설치
        $command = base_path('artisan');
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"],  // stderr
        ];
        $process = proc_open("php $command socialstream:install", $descriptorspec, $pipes);

        if (is_resource($process)) {
            // 선택지에 대한 응답 (순서대로 선택)
            $inputs = [
                "\n",  // 첫 번째 질문: 기본값 선택 (Laravel Breeze)
                "\n",  // 두 번째 질문: Laravel Jetstream 선택 (2번째 옵션)
                "\n",  // 세 번째 질문: Livewire 선택 (1번째 옵션)
                "n\n", // API 지원 비활성화
                "y\n", // Dark mode 활성화
                "n\n", // Email verification 비활성화
                "n\n", // Team support 비활성화
                "\n",  // Pest 선택 (2번째 옵션)
            ];

            foreach ($inputs as $input) {
                fwrite($pipes[0], $input);
                fflush($pipes[0]);
                // 각 입력 후 잠시 대기
                usleep(500000);  // 0.5초 대기
            }

            fclose($pipes[0]);

            // 출력 읽기
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // 오류 출력 읽기
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            // 프로세스 종료
            $return_value = proc_close($process);

            if ($return_value !== 0) {
                $this->error("Socialstream installation failed: $errors");
                return;
            }

            $this->info($output);
            $this->info('Socialstream and Jetstream installed successfully with predefined options.');
        } else {
            $this->error('Failed to start the Socialstream installation process.');
        }
    }

    private function installOpenAI()
    {
        if (class_exists(\OpenAI\Laravel\ServiceProvider::class)) {
            $this->info('OpenAI is already installed. Skipping...');
            return;
        }

        $this->info('Installing OpenAI...');
        $this->runComposerCommand('require openai-php/laravel');

        $this->info('Running OpenAI installation...');
        $this->call('openai:install');
    }

    private function installStartupfulPlugin()
    {
        if (Plugin::where('name', 'startupful-plugin')->exists()) {
            $this->info('Startupful Plugin is already installed. Skipping...');
            return;
        }

        // Original installation process...
        // Publish migrations
        $this->call('vendor:publish', [
            '--provider' => 'Startupful\StartupfulPlugin\StartupfulServiceProvider',
            '--tag' => 'startupful-migrations'
        ]);

        // Run migrations
        $this->call('migrate');

        $version = $this->getCurrentVersion();

        // Check if the plugins table exists
        if (Schema::hasTable('plugins')) {
            // Add Startupful Plugin to the plugins table
            Plugin::updateOrCreate(
                ['name' => 'startupful-plugin'],
                [
                    'version' => 'v' . ltrim($version, 'v'),
                    'description' => 'Core plugin for Startupful',
                    'developer' => 'startupful/startupful-plugin',
                    'is_active' => true,
                    'is_core' => true,
                    'installed_at' => now(),
                ]
            );

            $this->info('Startupful Plugin has been installed successfully.');
        } else {
            $this->error('The plugins table does not exist. Migration may have failed.');
            $this->info('Trying to run migrations manually...');
            
            // Force run migrations
            $this->call('migrate', ['--force' => true]);
            
            if (Schema::hasTable('plugins')) {
                $this->info('Migrations ran successfully.');
                // Add Startupful Plugin to the plugins table
                Plugin::updateOrCreate(
                    ['name' => 'startupful-plugin'],
                    [
                        'version' => 'v' . ltrim($version, 'v'),
                        'description' => 'Core plugin for Startupful',
                        'developer' => 'Startupful',
                        'is_active' => true,
                        'is_core' => true,
                        'installed_at' => now(),
                    ]
                );
                $this->info('Startupful Plugin has been installed successfully.');
            } else {
                $this->error('Failed to create the plugins table. Please check your database configuration and migration files.');
            }
        }
    }

    private function runComposerCommand($command)
    {
        $process = new Process(explode(' ', "composer $command"));
        $process->setWorkingDirectory(base_path());
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }
}