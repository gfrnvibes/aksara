<?php

namespace App\Filament\Actions;

use App\Models\Rapor;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PresensiHarian;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class KunciPresensiRapor
{
    /** @param Closure(mixed): ?Rapor $resolve */
    public static function make(bool $kunci, Closure $resolve): Action
    {
        return Action::make($kunci ? 'kunciPresensi' : 'bukaPresensi')
            ->label($kunci ? 'Kunci presensi' : 'Buka koreksi presensi')
            ->color($kunci ? 'success' : 'warning')->icon($kunci ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->visible(function ($record) use ($resolve, $kunci): bool {
                $rapor = $resolve($record);
                $user = Filament::auth()->user();

                return $rapor !== null && ! $rapor->trashed() && ($rapor->presensi_dikunci_at === null) === $kunci
                    && $user instanceof User && (AksesHarian::pengelola($user) || ($user->gtk?->is_aktif && $rapor->kelas?->wali_kelas_id === $user->gtk->id));
            })
            ->requiresConfirmation()->modalDescription($kunci ? 'S/I/A rapor dibekukan. Koreksi presensi harian tetap tercatat, tetapi rapor baru mengikuti koreksi setelah kunci dibuka.' : 'Rekap rapor akan disinkronkan kembali dengan presensi harian. Isi alasan pembukaan koreksi.')
            ->schema($kunci ? [] : [Textarea::make('alasan')->required()->maxLength(500)])
            ->action(function ($record, array $data) use ($resolve, $kunci): void {
                $user = Filament::auth()->user();
                abort_unless($user instanceof User, 403);
                $rapor = $resolve($record);
                abort_unless($rapor instanceof Rapor, 404);
                app(PresensiHarian::class)->kunciRapor($user, $rapor, $kunci, $data['alasan'] ?? null);
                Notification::make()->success()->title($kunci ? 'Presensi rapor dikunci' : 'Koreksi dibuka dan rekap disinkronkan')->send();
            });
    }
}
