<?php

namespace Startupful\StartupfulPlugin\Commands;

use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Startupful\StartupfulPlugin\Models\Plugin;
use Illuminate\Support\Facades\Schema;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;

class StartupfulInstallCommand extends Command
{
    protected $githubRepo;

    public $signature = 'startupful-plugin:install';

    public $description = 'Install the Startupful Plugin';

    protected $resourcePath = __DIR__ . '/../../resources/file/';

    public function __construct(GithubPluginRepository $githubRepo)
    {
        parent::__construct();
        $this->githubRepo = $githubRepo;
    }

    public function handle()
    {
        $this->info('Installing Startupful Plugin...');

        // Publish migrations
        $this->call('vendor:publish', [
            '--provider' => 'Startupful\StartupfulPlugin\StartupfulServiceProvider',
            '--tag' => 'startupful-migrations'
        ]);

        $this->callSilent('vendor:publish', ['--tag' => 'startupful-lang']);

        // Run migrations
        $this->call('migrate');

        // New steps
        $this->publishMigrations();
        $this->runMigrations();
        $this->updateAdminPanelProvider();
        $this->updateConfigApp();
        $this->copySetLocaleMiddleware();
        $this->copyLanguageController();
        $this->updateNavigationMenu();
        $this->updateWebRoutes();
        $this->updateAppServiceProvider();
        $this->updateHttpKernel();

        $this->copyAppBlade();
        $this->updateTailwindConfig();

        $this->copyStartupfulCss();
        $this->updateAppCss();

        $this->copyProfileViews();

        $version = $this->getCurrentVersion();

        if (Schema::hasTable('plugins')) {
            $existingPlugin = Plugin::where('name', 'startupful-plugin')->first();

            if ($existingPlugin) {
                // 플러그인이 이미 존재하는 경우, 아무 것도 변경하지 않음
                $this->info('Startupful Plugin is already installed.');
            } else {
                // 플러그인이 존재하지 않는 경우, 새로 생성
                Plugin::create([
                    'name' => 'startupful-plugin',
                    'version' => 'v' . ltrim($version, 'v'),
                    'description' => 'Core plugin for Startupful',
                    'developer' => 'startupful/startupful-plugin',
                    'is_active' => true,
                    'is_core' => true,
                    'installed_at' => now(),
                ]);
                $this->info('Startupful Plugin has been installed successfully.');
            }
        } else {
            $this->error('The plugins table does not exist. Migration may have failed.');
            $this->info('Trying to run migrations manually...');
            
            // Force run migrations
            $this->call('migrate', ['--force' => true]);
            
            if (Schema::hasTable('plugins')) {
                $this->info('Migrations ran successfully.');
                Plugin::create([
                    'name' => 'startupful-plugin',
                    'version' => 'v' . ltrim($version, 'v'),
                    'description' => 'Core plugin for Startupful',
                    'developer' => 'startupful/startupful-plugin',
                    'is_active' => true,
                    'is_core' => true,
                    'installed_at' => now(),
                ]);
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

        $replacements = [
            "'locale' => 'en'" => "'locale' => env('APP_LOCALE', 'en')",
            "'fallback_locale' => 'en'" => "'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en')",
            "'faker_locale' => 'en_US'" => "'faker_locale' => env('APP_FAKER_LOCALE', 'en_US')",
        ];

        $newConfig = $config;
        foreach ($replacements as $search => $replace) {
            $newConfig = str_replace($search, $replace, $newConfig);
        }

        if (!str_contains($newConfig, "'available_locales'")) {
            $newConfig = str_replace(
                "'locale' => env('APP_LOCALE', 'en'),",
                "'locale' => env('APP_LOCALE', 'en'),\n\n    'available_locales' => ['en', 'ko', 'de', 'fr', 'hi', 'ja', 'pt', 'th', 'tl', 'zh'],",
                $newConfig
            );
        }

        if ($newConfig !== $config) {
            file_put_contents($configPath, $newConfig);
            $this->info('Updated config/app.php with environment variables and available locales.');
        } else {
            $this->info('Config/app.php is already up to date.');
        }

        $this->updateEnvFile();
    }

    private function updateEnvFile(): void
    {
        $envPath = base_path('.env');
        $env = file_get_contents($envPath);

        $envUpdates = [
            'APP_LOCALE' => 'en',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'en_US',
        ];

        foreach ($envUpdates as $key => $value) {
            if (!preg_match("/^{$key}=/m", $env)) {
                $env .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $env);
        $this->info('Updated .env file with new locale variables.');
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

        if (!str_contains($content, '$this->app->bind(\'locale\'')) {
            $addition = "\n        \$this->app->bind('locale', function (\$app) {
                \$locale = request()->cookie('locale') ?? session('locale') ?? config('app.locale');
                if (!in_array(\$locale, config('app.available_locales'))) {
                    \$locale = config('app.fallback_locale');
                }
                app()->setLocale(\$locale);
                return \$locale;
            });\n";

            $content = preg_replace(
                '/(public function boot\(\).*?\{)/s',
                "$1\n$addition",
                $content
            );

            File::put($path, $content);
            $this->info('Updated AppServiceProvider.php with locale binding.');
        } else {
            $this->info('Locale binding already exists in AppServiceProvider.php.');
        }
    }

    private function updateHttpKernel(): void
    {
        $path = app_path('Http/Kernel.php');

        if (!File::exists($path)) {
            $this->warn('Http/Kernel.php not found.');
            return;
        }

        $originalContent = File::get($path);
        $content = $originalContent;

        if (!str_contains($content, 'SetLocale::class')) {
            $pattern = "/('web'\s*=>\s*\[)([\s\S]*?)\],/";
            $replacement = "$1$2            \App\Http\Middleware\SetLocale::class,\n        ],";
            
            $updatedContent = preg_replace($pattern, $replacement, $content);
            
            if ($updatedContent !== $content) {
                // Create a backup
                File::put($path . '.bak', $originalContent);
                
                File::put($path, $updatedContent);
                $this->info('Updated Http/Kernel.php with SetLocale middleware in web group.');
                $this->info('A backup of the original file has been created as Http/Kernel.php.bak');
            } else {
                $this->warn('Failed to automatically update Http/Kernel.php. Please add the following line manually to the web middleware group:');
                $this->warn('\App\Http\Middleware\SetLocale::class,');
            }
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
        $latestVersion = $this->githubRepo->getLatestVersion('startupful/startupful-plugin');
        
        // 모든 방법이 실패한 경우 기본값 반환
        $this->warn("Unable to determine the current version. Using default version.");
        return '0.1.0'; // 적절한 기본 버전으로 변경하세요
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

    private function copyAppBlade(): void
    {
        $sourcePath = __DIR__ . '/../../resources/file/app.blade.php';
        $destinationPath = resource_path('views/layouts/app.blade.php');
        
        if (File::exists($sourcePath)) {
            File::copy($sourcePath, $destinationPath);
            $this->info('Copied app.blade.php to ' . $destinationPath);
        } else {
            $this->warn('Source file for app.blade.php not found at ' . $sourcePath);
        }
    }

    private function updateTailwindConfig(): void
    {
        $configPath = base_path('tailwind.config.js');
        
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            
            // content 배열에 새 경로 추가
            if (!str_contains($content, "'./vendor/startupful/**/*.blade.php'")) {
                $content = preg_replace(
                    "/(\s*'\.\/resources\/views\/\*\*\/\*\.blade\.php',\s*)\]/",
                    "$1    './vendor/startupful/**/*.blade.php',\n    ]",
                    $content
                );
            }

            if (!str_contains($content, "darkMode: 'class'")) {
                $content = preg_replace(
                    "/(export\s+default\s*{\s*)/",
                    "$1\n  darkMode: 'class',\n",
                    $content
                );
            }
            
            // theme.extend.colors에 darkblue 추가
            if (!str_contains($content, "'darkblue':")) {
                $darkblueColors = "'darkblue': {
                    50: '#F0F1F5',
                    100: '#D9DAE1',
                    200: '#B3B5BE',
                    300: '#8D909B',
                    400: '#666A78',
                    500: '#2B2C31',
                    600: '#1E1F23',
                    700: '#1D2025',
                    800: '#191B1E',
                    900: '#15161A',
                },";
                
                $content = preg_replace(
                    "/(theme:\s*{\s*extend:\s*{)/",
                    "$1\n            colors: {\n                $darkblueColors\n            },",
                    $content
                );
            }
            
            File::put($configPath, $content);
            $this->info('Updated tailwind.config.js with new content path and darkblue color theme.');
        } else {
            $this->warn('tailwind.config.js not found. Please update it manually.');
        }
    }

    private function copyStartupfulCss(): void
    {
        $sourcePath = __DIR__ . '/../../resources/file/startupful.css';
        $destinationPath = resource_path('css/startupful.css');
        
        if (File::exists($sourcePath)) {
            File::ensureDirectoryExists(dirname($destinationPath));
            File::copy($sourcePath, $destinationPath);
            $this->info('Copied startupful.css to ' . $destinationPath);
        } else {
            $this->warn('Source file for startupful.css not found at ' . $sourcePath);
        }
    }

    private function updateAppCss(): void
    {
        $appCssPath = resource_path('css/app.css');
        
        if (File::exists($appCssPath)) {
            $content = File::get($appCssPath);
            
            if (!Str::contains($content, "@import './startupful.css';")) {
                $updatedContent = "@import './startupful.css';\n\n" . $content;
                File::put($appCssPath, $updatedContent);
                $this->info('Updated app.css with import for startupful.css');
            } else {
                $this->info('app.css already imports startupful.css');
            }
        } else {
            $this->warn('app.css not found. Please add the import manually.');
        }
    }

    protected function copyProfileViews(): void
    {
        $sourcePath = __DIR__ . '/../../resources/file/profile';
        $destinationPath = resource_path('views/profile');

        if (!File::isDirectory($sourcePath)) {
            $this->warn("Source directory for profile views not found at: $sourcePath");
            return;
        }

        if (!File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $files = File::files($sourcePath);

        foreach ($files as $file) {
            $destinationFile = $destinationPath . '/' . $file->getFilename();
            
            File::copy($file->getPathname(), $destinationFile, true);  // true parameter forces overwrite
            $this->info("Copied and overwritten: {$file->getFilename()} in views/profile directory.");
        }

        $this->info('Profile view files have been copied and overwritten successfully.');
    }
}