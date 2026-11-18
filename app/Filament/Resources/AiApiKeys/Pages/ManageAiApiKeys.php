<?php

namespace App\Filament\Resources\AiApiKeys\Pages;

use App\Filament\Resources\AiApiKeys\AiApiKeyResource;
use App\Models\AiApiKey;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAiApiKeys extends ManageRecords
{
    protected static string $resource = AiApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imporEnv')
                ->label('Impor dari .env')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => filled(config('services.gemini.key')) && AiApiKey::query()->doesntExist())
                ->action(function (): void {
                    AiApiKey::query()->create([
                        'nama' => 'Gemini (.env)',
                        'provider' => 'gemini',
                        'api_key' => (string) config('services.gemini.key'),
                        'daftar_model' => (string) config('services.gemini.model', 'gemini-2.5-flash-lite,gemini-2.5-flash'),
                        'timeout_detik' => max(5, (int) config('services.gemini.timeout', 15)),
                        'prioritas' => 1,
                        'is_aktif' => true,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('API key dari .env berhasil diimpor')
                        ->send();
                }),
            CreateAction::make()
                ->label('Tambah API Key')
                ->modalWidth('xl'),
        ];
    }
}
