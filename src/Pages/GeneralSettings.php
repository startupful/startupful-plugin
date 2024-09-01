<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Startupful\StartupfulPlugin\Models\PluginSetting;

class GeneralSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'startupful-general-settings';

    protected static string $view = 'startupful::pages.general-settings';

    public $app_language;
    public $plugin_key;
    protected $mainServerUrl = 'https://startupful.io';

    public function mount(): void
    {
        $pluginSetting = PluginSetting::where('plugin_id', 1)->where('key', 'plugin-key')->first();
        
        $this->form->fill([
            'app_language' => config('app.locale'),
            'plugin_key' => $pluginSetting ? $pluginSetting->value : '',
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return __('startupful-plugin.settings');
    }

    public function getTitle(): string
    {
        return __('startupful-plugin.settings');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('startupful-plugin.save'))
                ->action('submit')
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('app_language')
                    ->label(__('startupful-plugin.app_language'))
                    ->options($this->getLanguageOptions())
                    ->required()
                    ->helperText(__('startupful-plugin.select_language')),
                Forms\Components\TextInput::make('plugin_key')
                    ->label(__('startupful-plugin.plugin_key'))
                    ->helperText(__('startupful-plugin.plugin_key_info'))
                    ->disabled(fn () => $this->isVerified()),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('verifyKey')
                    ->label(fn () => $this->isVerified() ? __('startupful-plugin.plugin_key_remove') : __('startupful-plugin.plugin_key_verify'))
                        ->action('verifyOrRemoveKey')
                        ->color(fn () => $this->isVerified() ? 'danger' : 'primary')
                ]),
            ]);
    }

    public function verifyOrRemoveKey(): void
    {
        if ($this->isVerified()) {
            $this->removeSubscription();
        } else {
            $this->verifySubscription($this->plugin_key);
        }
    }

    public function verifySubscription($pluginKey): void
    {
        try {
            Log::info('Attempting to verify subscription', ['plugin_key' => $pluginKey, 'domain' => request()->getHost()]);
            
            $response = Http::post($this->mainServerUrl . '/api/verify-subscription', [
                'paddle_id' => $pluginKey,
                'domain' => request()->getHost(),
            ]);
            
            Log::info('Received response from server', ['status' => $response->status(), 'body' => $response->body()]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                $this->savePluginKey($pluginKey);
                Notification::make()
                    ->title($responseData['message'] ?? 'Subscription verified successfully')
                    ->success()
                    ->send();
            } else {
                $errorMessage = $response->json()['message'] ?? $response->body() ?? 'Unknown error';
                Notification::make()
                    ->title('Failed to verify subscription')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (QueryException $e) {
            Log::error('Database error while saving plugin key', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            Notification::make()
                ->title('Failed to save plugin key')
                ->body('A database error occurred. Please try again or contact support.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Exception occurred while verifying subscription', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            Notification::make()
                ->title('Failed to verify subscription')
                ->body('An unexpected error occurred. Please try again or contact support.')
                ->danger()
                ->send();
        }
    }

    private function savePluginKey($key): void
    {
        PluginSetting::updateOrCreate(
            ['plugin_id' => 1, 'key' => 'plugin-key'],
            [
                'plugin_name' => 'startupful_plugin',
                'value' => $key
            ]
        );
    }

    public function removeSubscription(): void
    {
        try {
            $response = Http::post($this->mainServerUrl . '/api/remove-subscription', [
                'domain' => request()->getHost(),
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                $this->deletePluginKey();
                Notification::make()
                    ->title($responseData['message'] ?? 'Subscription removed successfully')
                    ->success()
                    ->send();
            } else {
                $errorMessage = $response->json()['message'] ?? $response->body() ?? 'Unknown error';
                Notification::make()
                    ->title('Failed to remove subscription')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to remove subscription')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function deletePluginKey(): void
    {
        PluginSetting::where('plugin_id', 1)
            ->where('key', 'plugin-key')
            ->delete();
    }

    private function isVerified(): bool
    {
        return PluginSetting::where('plugin_id', 1)
            ->where('key', 'plugin-key')
            ->exists();
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $locale = $data['app_language'];
        $fakerLocale = $this->getFakerLocale($locale);

        // Update .env file
        $this->updateEnvFile('APP_LOCALE', $locale);
        $this->updateEnvFile('APP_FALLBACK_LOCALE', $locale);
        $this->updateEnvFile('APP_FAKER_LOCALE', $fakerLocale);

        // Clear config cache
        Artisan::call('config:clear');

        // Show a success notification
        Notification::make()
            ->title('Settings updated successfully')
            ->success()
            ->send();
    }

    private function updateEnvFile($key, $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            file_put_contents($path, preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                file_get_contents($path)
            ));
        }
    }

    private function getLanguageOptions(): array
    {
        return [
            'en' => 'English',
            'ko' => '한국어',
            'zh' => '中文',
            'ja' => '日本語',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'pt' => 'Português',
            'hi' => 'हिंदी',
            'fil' => 'Filipino',
            'th' => 'ภาษาไทย',
        ];
    }

    private function getFakerLocale($locale): string
    {
        $fakerLocales = [
            'en' => 'en_US',
            'ko' => 'ko_KR',
            'zh' => 'zh_CN',
            'ja' => 'ja_JP',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'pt' => 'pt_BR',
            'hi' => 'hi_IN',
            'fil' => 'en_PH',
            'th' => 'th_TH',
        ];

        return $fakerLocales[$locale] ?? 'en_US';
    }
}