<?php

namespace Startupful\StartupfulPlugin\Commands;

use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Startupful\StartupfulPlugin\Models\Plugin;
use Illuminate\Support\Facades\Schema;

class StartupfulInstallCommand extends Command
{
    public $signature = 'startupful-plugin:install';

    public $description = 'Install the Startupful Plugin';

    protected $resourcePath = __DIR__ . '/../../resources/file/';

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

        // New steps
        $this->updateConfigApp();
        $this->copySetLocaleMiddleware();
        $this->copyLanguageController();
        $this->updateNavigationMenu();
        $this->updateWebRoutes();
        $this->updateAppServiceProvider();
        $this->updateBootstrapApp();
        $this->updateHttpKernel();

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

    private function updateConfigApp(): void
    {
        $configPath = config_path('app.php');
        $config = file_get_contents($configPath);

        if (!str_contains($config, "'available_locales'")) {
            $newConfig = str_replace(
                "'locale' => 'en',",
                "'locale' => 'en',\n\n    'available_locales' => ['en', 'ko', 'de', 'fr', 'hi', 'ja', 'pt', 'th', 'tl', 'zh'],",
                $config
            );
            file_put_contents($configPath, $newConfig);
            $this->info('Updated config/app.php with available locales.');
        } else {
            $this->info('Available locales already exist in config/app.php.');
        }
    }

    private function copySetLocaleMiddleware(): void
    {
        $sourcePath = $this->resourcePath . 'SetLocale.php';
        $destinationPath = app_path('Http/Middleware/SetLocale.php');
        
        $this->copyFile($sourcePath, $destinationPath, 'SetLocale middleware');
    }

    private function copyLanguageController(): void
    {
        $sourcePath = $this->resourcePath . 'LanguageController.php';
        $destinationPath = app_path('Http/Controllers/LanguageController.php');
        
        $this->copyFile($sourcePath, $destinationPath, 'LanguageController');
    }

    private function updateNavigationMenu(): void
    {
        $sourcePath = $this->resourcePath . 'navigation-menu-addition.blade.php';
        $destinationPath = resource_path('views/navigation-menu.blade.php');

        if (!File::exists($sourcePath) || !File::exists($destinationPath)) {
            $this->warn('Required files for navigation menu update not found.');
            return;
        }

        $content = File::get($destinationPath);
        $addition = File::get($sourcePath);

        if (!str_contains($content, trim($addition))) {
            $updatedContent = str_replace(
                '<!-- Teams Dropdown -->',
                $addition . "\n\n            <!-- Teams Dropdown -->",
                $content
            );

            File::put($destinationPath, $updatedContent);
            $this->info('Updated navigation-menu.blade.php with language dropdown.');
        } else {
            $this->info('Language dropdown already exists in navigation-menu.blade.php.');
        }
    }

    private function updateWebRoutes(): void
    {
        $routesPath = base_path('routes/web.php');
        
        if (File::exists($routesPath)) {
            $content = File::get($routesPath);
            
            if (!str_contains($content, 'LanguageController')) {
                $addition = "\nuse App\Http\Controllers\LanguageController;\n\nRoute::middleware(['web'])->group(function () {\n    Route::get('language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');\n});\n";
                
                File::append($routesPath, $addition);
                $this->info('Updated routes/web.php with language switch route.');
            } else {
                $this->info('Language switch route already exists in routes/web.php.');
            }
        } else {
            $this->warn('routes/web.php not found. Please add the language switch route manually.');
        }
    }

    private function updateAppServiceProvider(): void
    {
        $path = app_path('Providers/AppServiceProvider.php');

        if (!File::exists($path)) {
            $this->warn('AppServiceProvider.php not found.');
            return;
        }

        $content = File::get($path);

        if (!str_contains($content, '$this->app->singleton(\'locale\'')) {
            $addition = "\n        \$this->app->singleton('locale', function (\$app) {
                \$locale = session('locale', config('app.locale'));
                app()->setLocale(\$locale);
                return \$locale;
            });\n";

            $content = preg_replace(
                '/(public function boot\(\).*?\{)/s',
                "$1\n$addition",
                $content
            );

            File::put($path, $content);
            $this->info('Updated AppServiceProvider.php with locale singleton.');
        } else {
            $this->info('Locale singleton already exists in AppServiceProvider.php.');
        }
    }

    private function updateBootstrapApp(): void
    {
        $path = base_path('bootstrap/app.php');
    
        if (!File::exists($path)) {
            $this->warn('bootstrap/app.php not found.');
            return;
        }
    
        $content = File::get($path);
    
        if (!str_contains($content, 'SetLocale::class')) {
            $addition = "\n\$app->middleware([
        // ... other middleware
        \\App\\Http\\Middleware\\SetLocale::class,
    ]);\n";
    
            // Laravel 10 버전에 맞는 코드 추가
            $laravelTenAddition = "\n\$app->alias('middleware', Illuminate\Contracts\Http\Kernel::class);
    \$app->make('middleware')->prependMiddleware(\\App\\Http\\Middleware\\SetLocale::class);\n";
    
            $content .= $addition . $laravelTenAddition;
    
            File::put($path, $content);
            $this->info('Updated bootstrap/app.php with SetLocale middleware for both Laravel versions.');
        } else {
            $this->info('SetLocale middleware already exists in bootstrap/app.php.');
        }
    }

    private function updateHttpKernel(): void
    {
        $path = app_path('Http/Kernel.php');

        if (!File::exists($path)) {
            $this->warn('Http/Kernel.php not found.');
            return;
        }

        $content = File::get($path);

        if (!str_contains($content, 'SetLocale::class')) {
            $addition = "\n        \\App\\Http\\Middleware\\SetLocale::class,";
            $content = preg_replace(
                '/(protected \$middleware = \[.*?)\n    \];/s',
                "$1$addition\n    ];",
                $content
            );

            File::put($path, $content);
            $this->info('Updated Http/Kernel.php with SetLocale middleware.');
        } else {
            $this->info('SetLocale middleware already exists in Http/Kernel.php.');
        }
    }

    private function copyFile($sourcePath, $destinationPath, $fileName): void
    {
        if (File::exists($sourcePath)) {
            File::ensureDirectoryExists(dirname($destinationPath));
            File::copy($sourcePath, $destinationPath);
            $this->info("Copied $fileName to " . $destinationPath);
        } else {
            $this->warn("Source file for $fileName not found at $sourcePath.");
        }
    }

    private function showAdditionalInstructions(): void
    {
        $this->info('Please add the following route to your routes/web.php file:');
        $this->info("Route::get('language/{locale}', [App\\Http\\Controllers\\LanguageController::class, 'switch'])->name('language.switch');");

        $this->info('Please add SetLocale middleware to your app/Http/Kernel.php file:');
        $this->info("protected \$middlewareGroups = [
    'web' => [
        // ...
        \\App\\Http\\Middleware\\SetLocale::class,
    ],
];");
    }

    private function getCurrentVersion(): string
    {
        $composerJson = File::get(__DIR__ . '/../../composer.json');
        $composerData = json_decode($composerJson, true);
        return $composerData['version'] ?? '0.1.0';  // 기본값으로 1.0.0 사용
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
}