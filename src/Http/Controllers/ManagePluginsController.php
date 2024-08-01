<?php

namespace Startupful\StartupfulPlugin\Http\Controllers;

use Filament\Forms;
use Filament\Tables;
use Livewire\Component;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Support\Facades\Session;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Support\Facades\App;
use Startupful\StartupfulPlugin\Models\Plugin;
use Filament\Notifications\Notification;
use Startupful\StartupfulPlugin\StartupfulPlugin;
use Startupful\StartupfulPlugin\Services\GithubPluginRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class ManagePluginsController
{
    use Tables\Concerns\InteractsWithTable;

    protected $mainServerUrl = 'https://startupful.io';
    protected $githubRepo;

    public function __construct(GithubPluginRepository $githubRepo)
    {
        $this->githubRepo = $githubRepo;
    }

    protected function getGithubRepo(): GithubPluginRepository
    {
        return App::make(GithubPluginRepository::class);
    }

    protected function getLatestStartupfulPluginVersion(): ?string
    {
        return $this->githubRepo->getLatestVersion('startupful/startupful-plugin');
    }

    protected function getCurrentStartupfulPluginVersion(): string
    {
        // 현재 버전을 가져오는 로직을 구현해야 합니다.
        // 예를 들어, 설정 파일이나 데이터베이스에서 읽어올 수 있습니다.
        // 임시로 하드코딩된 값을 반환합니다.
        return '1.0.0';
    }

    public function table(Table $table): Table
    {
        $pluginKey = Session::get('plugin_key', '');
        $isVerified = Session::get('is_verified', false);

        return $table
            ->query(Plugin::query()->orderByDesc('is_core')->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(100),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled(fn (Plugin $record) => $record->is_core),
                Tables\Columns\TextColumn::make('installed_at')
                    ->dateTime(),
            ])
            ->searchable(false)
            ->actions([
                Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Plugin $record) => $this->updatePlugin($record))
                    ->disabled(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version;
                    })
                    ->tooltip(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version
                            ? 'Plugin is up to date'
                            : 'Update available';
                    })
                    ->color(function (Plugin $record) {
                        $latestVersion = StartupfulPlugin::getGithubRepo()->getLatestVersion($record->developer);
                        return $latestVersion === $record->version
                            ? 'gray'
                            : 'primary';
                    }),
                DeleteAction::make()
                    ->action(function (Plugin $record) {
                        $this->uninstallPlugin($record);
                    })
                    ->hidden(fn (Plugin $record) => $record->is_core)
            ])
            ->headerActions([
                Action::make('pluginKeyAction')
                    ->label('Plugin Key')
                    ->form([
                        TextInput::make('plugin_key')
                            ->label('Plugin Key')
                            ->required()
                            ->disabled($isVerified)
                            ->default($pluginKey),
                    ])
                    ->action(function (array $data) use ($isVerified) {
                        if ($isVerified) {
                            $this->removeSubscription();
                        } else {
                            $this->verifySubscription($data['plugin_key']);
                        }
                    })
                    ->button()
                    ->label(fn () => $isVerified ? 'Plugin Key Remove' : 'Plugin Key Verify')
                    ->color(fn () => $isVerified ? 'danger' : 'primary'),
            ]);
    }

    public function getInstalledPlugins()
    {
        return Plugin::all();
    }

    protected function getUpdateController()
    {
        return App::make(PluginUpdateController::class);
    }

    protected function getUninstallController()
    {
        return App::make(PluginUninstallController::class);
    }

    public function updatePlugin(Plugin $plugin): void
    {
        try {
            $this->getUpdateController()->updatePlugin($plugin);
        } catch (\Exception $e) {
            Notification::make()
                ->title("Failed to update plugin")
                ->body("Error: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function uninstallPlugin($plugin): void
    {
        try {
            $this->getUninstallController()->uninstallPlugin($plugin);
        } catch (\Exception $e) {
            Notification::make()
                ->title("Failed to uninstall plugin")
                ->body("Error: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }



    public function verifySubscription($pluginKey)
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
                Session::put('plugin_key', $pluginKey);
                Session::put('is_verified', true);
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
        } catch (\Exception $e) {
            Log::error('Exception occurred while verifying subscription', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            Notification::make()
                ->title('Failed to verify subscription')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeSubscription()
    {
        try {
            $response = Http::post($this->mainServerUrl . '/api/remove-subscription', [
                'domain' => request()->getHost(),
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                Session::forget('plugin_key');
                Session::forget('is_verified');
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
}