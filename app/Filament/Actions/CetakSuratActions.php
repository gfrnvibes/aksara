<?php

namespace App\Filament\Actions;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Models\MutasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The letter actions: ask for the letter number, purpose and date, then open the letter to print or download it.
 */
class CetakSuratActions
{
    public const string MODE_CETAK = 'cetak';

    public const string MODE_UNDUH = 'unduh';

    /**
     * The letters of one student, for the student table.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            static::keteranganAktif(),
            static::transkrip(),
        ])
            ->label('Surat')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray');
    }

    public static function keteranganAktif(): Action
    {
        return static::make('suratKeteranganAktif', 'Surat Keterangan Aktif', 'surat.keterangan-aktif', denganKeperluan: true)
            ->visible(fn (Siswa $record): bool => $record->status === StatusSiswa::Aktif && Gate::allows('view', $record));
    }

    public static function transkrip(): Action
    {
        return static::make('transkripNilai', 'Transkrip Nilai', 'surat.transkrip', denganKeperluan: false)
            ->visible(fn (Siswa $record): bool => Gate::allows('view', $record) && Gate::allows('viewAny', Rapor::class));
    }

    public static function suratPindah(): Action
    {
        return static::make('suratPindah', 'Surat Pindah', 'surat.pindah', denganKeperluan: false)
            ->icon(Heroicon::OutlinedDocumentText)
            ->fillForm(fn (MutasiSiswa $record): array => ['nomor' => $record->nomor_surat, 'tanggal' => $record->tanggal, 'mode' => static::MODE_CETAK])
            ->visible(fn (MutasiSiswa $record): bool => $record->jenis === JenisMutasi::Pindah && Gate::allows('view', $record));
    }

    protected static function make(string $nama, string $label, string $route, bool $denganKeperluan): Action
    {
        return Action::make($nama)
            ->label($label)
            ->icon(Heroicon::OutlinedPrinter)
            ->modalHeading($label)
            ->modalWidth('md')
            ->modalSubmitActionLabel('Buat surat')
            ->schema([
                TextInput::make('nomor')
                    ->label('Nomor Surat')
                    ->placeholder('B-123/Mts.10.05/PP.00/'.now()->year)
                    ->maxLength(255),
                ...($denganKeperluan ? [
                    TextInput::make('keperluan')
                        ->placeholder('Contoh: pengajuan beasiswa PIP')
                        ->maxLength(255),
                ] : []),
                DatePicker::make('tanggal')
                    ->label('Tanggal Surat')
                    ->default(now())
                    ->required(),
                Radio::make('mode')
                    ->label('Keluaran')
                    ->options([static::MODE_CETAK => 'Cetak di browser', static::MODE_UNDUH => 'Unduh PDF'])
                    ->default(static::MODE_CETAK)
                    ->required(),
            ])
            ->action(function (array $data, Model $record) use ($route, $label): ?RedirectResponse {
                $parameter = array_filter([
                    'nomor' => $data['nomor'] ?? null,
                    'keperluan' => $data['keperluan'] ?? null,
                    'tanggal' => filled($data['tanggal'] ?? null) ? Carbon::parse($data['tanggal'])->toDateString() : null,
                ], filled(...));

                if (($data['mode'] ?? null) === static::MODE_UNDUH) {
                    return redirect()->to(route($route, [$record, ...$parameter, 'format' => 'pdf']));
                }

                Notification::make()
                    ->success()
                    ->title("{$label} siap dicetak")
                    ->actions([
                        Action::make('buka')
                            ->label('Buka halaman cetak')
                            ->button()
                            ->url(route($route, [$record, ...$parameter]), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();

                return null;
            });
    }
}
