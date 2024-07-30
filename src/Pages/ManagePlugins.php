<?php

namespace Startupful\StartupfulPlugin\Pages;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Startupful\StartupfulPlugin\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Concerns\InteractsWithTable;

class ManagePlugins extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string $view = 'startupful::pages.manage-plugins';
    protected static ?string $navigationGroup = 'Startupful Plugin';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'startupful-manage-plugins';

    public static function getNavigationLabel(): string
    {
        return 'Manage Plugins';
    }

    public function getInstalledPlugins()
    {
        return Plugin::all();
    }

     public function table(Table $table): Table
    {
        return $table
            ->query(Plugin::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('developer'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('installed_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make()
                    ->action(function (Plugin $record) {
                        
                        try {
                            $this->uninstallPlugin($record);
                            Notification::make()
                                ->title("Plugin '{$record->name}' uninstalled successfully.")
                                ->success()
                                ->send();
                            
                            // 테이블 새로고침
                            $this->dispatch('refresh');
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title("Failed to uninstall plugin")
                                ->body("Error: " . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function (Collection $records) {
                        foreach ($records as $record) {
                            $this->uninstallPlugin($record);
                        }
                    }),
            ]);
    }

    public function uninstallPlugin($plugin): void
    {
        if (!$plugin instanceof Plugin) {
            $plugin = Plugin::findOrFail($plugin);
        }
        
        try {
            $packageName = $plugin->developer;  // developer 필드 사용

            // 1. 먼저 파일 시스템에서 패키지 삭제
            $this->cleanupPluginFiles($plugin->name, $packageName);

            // 2. Composer를 사용하여 패키지 제거 (옵션으로 처리)
            $command = ['composer', 'remove', $packageName, '--no-interaction', '--no-update'];
        
            $env = getenv();
            $env['HOME'] = base_path();
            $env['COMPOSER_HOME'] = sys_get_temp_dir() . '/.composer';

            $process = new Process($command, base_path(), $env);
            $process->setTimeout(300);
            $process->run();

            // Composer 제거 실패해도 계속 진행
            if (!$process->isSuccessful()) {
                \Log::warning("Failed to remove package via Composer: " . $process->getErrorOutput());
            }

            // 3. AdminPanelProvider.php 파일에서 플러그인 관련 코드 제거
            $this->removePluginFromAdminPanelProvider($plugin);

            // 4. 데이터베이스에서 플러그인 정보 삭제
            $deleteResult = $plugin->delete();

            if (!$deleteResult) {
                throw new \Exception("Failed to delete plugin from database");
            }

        } catch (\Exception $e) {
            \Log::error("Error uninstalling plugin: " . $e->getMessage());
            throw $e;
        }
    }

    protected function removePluginFromAdminPanelProvider(Plugin $plugin): void
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        if (!file_exists($providerPath)) {
            throw new \Exception("AdminPanelProvider.php file not found");
        }

        try {
            $content = file_get_contents($providerPath);
            if ($content === false) {
                throw new \Exception("Failed to read AdminPanelProvider.php");
            }
            
            // (위의 코드와 동일)
            
            if (file_put_contents($providerPath, $content) === false) {
                throw new \Exception("Failed to write updated content to AdminPanelProvider.php");
            }
        } catch (\Exception $e) {
            // 로그 기록 또는 사용자에게 알림
            \Log::error("Error removing plugin from AdminPanelProvider: " . $e->getMessage());
            throw $e;
        }
    }

    protected function cleanupPluginFiles($pluginName, $packageName): void
    {   
        // vendor 디렉토리에서 패키지 삭제
        $vendorDir = base_path('vendor/' . str_replace('/', DIRECTORY_SEPARATOR, $packageName));
        if (is_dir($vendorDir)) {
            try {
                File::deleteDirectory($vendorDir);
                \Log::info("Successfully deleted vendor directory: " . $vendorDir);
            } catch (\Exception $e) {
                \Log::warning("Failed to delete vendor directory: " . $e->getMessage());
            }
        } else {
            \Log::info("Vendor directory not found: " . $vendorDir);
        }
        
        // composer.json에서 패키지 제거
        $composerJsonPath = base_path('composer.json');
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            if (isset($composerJson['require'][$packageName])) {
                unset($composerJson['require'][$packageName]);
                file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                \Log::info("Removed package from composer.json: " . $packageName);
            }
        }
        
        // 추가적인 파일 정리 로직이 필요하다면 여기에 구현
        // 예: 설정 파일, 캐시 등
    }

    public function mount(): void
    {
    }

    public function refresh(): void
    {
        // 이 메서드는 테이블을 새로고침하는 데 사용됩니다.
        $this->resetTable();
    }
}