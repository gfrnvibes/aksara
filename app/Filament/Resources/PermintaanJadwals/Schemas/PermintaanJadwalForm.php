<?php

namespace App\Filament\Resources\PermintaanJadwals\Schemas;

use App\Enums\Hari;
use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Models\Gtk;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Services\Jadwal\PolaJam;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The scheduling wish form, used by operators for any teacher and by teachers for themselves.
 */
class PermintaanJadwalForm
{
    /**
     * @param  (callable(): ?Gtk)|null  $guruSendiri  The teacher filling in their own wishes; null lets the user pick one.
     */
    public static function configure(Schema $schema, ?callable $guruSendiri = null): Schema
    {
        $opsiJp = fn (): array => static::opsiJp();

        return $schema
            ->components([
                Select::make('gtk_id')
                    ->label('Guru')
                    ->relationship('gtk', 'nama', fn ($query) => $query->where('is_aktif', true)->orderBy('nama'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible($guruSendiri === null)
                    ->required($guruSendiri === null),
                ToggleButtons::make('jenis')
                    ->label('Permintaan')
                    ->options(JenisPermintaanJadwal::class)
                    ->default(JenisPermintaanJadwal::TidakBisa)
                    ->inline()
                    ->required(),
                CheckboxList::make('hari')
                    ->label('Hari')
                    ->options(Hari::class)
                    ->columns(3)
                    ->helperText('Kosongkan jika berlaku untuk semua hari.')
                    ->dehydrateStateUsing(fn (?array $state): ?array => blank($state) ? null : array_values(array_map(fn (mixed $hari): int => $hari instanceof Hari ? $hari->value : (int) $hari, $state))),
                Grid::make(2)->schema([
                    Select::make('jp_mulai')
                        ->label('Dari JP ke-')
                        ->options($opsiJp)
                        ->placeholder('JP pertama'),
                    Select::make('jp_selesai')
                        ->label('Sampai JP ke-')
                        ->options($opsiJp)
                        ->placeholder('JP terakhir')
                        ->gte('jp_mulai'),
                ]),
                Select::make('mata_pelajaran_id')
                    ->label('Hanya untuk mata pelajaran')
                    ->options(fn (Get $get): array => static::opsiMapel($guruSendiri !== null ? $guruSendiri()?->id : $get('gtk_id')))
                    ->placeholder('Semua mata pelajaran guru ini'),
                ToggleButtons::make('sifat')
                    ->options(SifatPermintaanJadwal::class)
                    ->default(SifatPermintaanJadwal::Diusahakan)
                    ->helperText('Pilih Wajib hanya jika benar-benar tidak bisa, misalnya mengajar di sekolah lain. Permintaan wajib yang terlalu banyak membuat jadwal sulit disusun.')
                    ->inline()
                    ->required(),
                TextInput::make('keterangan')
                    ->label('Alasan / Keterangan')
                    ->placeholder('Mengajar di SMP lain')
                    ->maxLength(255),
                Toggle::make('is_aktif')
                    ->label('Berlaku')
                    ->helperText('Matikan jika permintaan ini sedang tidak berlaku, tanpa menghapusnya.')
                    ->default(true),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, string>
     */
    protected static function opsiJp(): array
    {
        $pola = PolaJam::muat();
        $terbanyak = max(array_map(fn (Hari $hari): int => count($pola->jp($hari)), $pola->hari()) ?: [9]);

        return collect(range(1, $terbanyak))->mapWithKeys(fn (int $jp): array => [$jp => "JP {$jp}"])->all();
    }

    /**
     * The subjects the teacher teaches this academic year, or every active subject when no teacher is chosen.
     *
     * @return array<int, string>
     */
    protected static function opsiMapel(mixed $gtkId): array
    {
        if (blank($gtkId)) {
            return MataPelajaran::query()->aktifBerurutan()->pluck('nama', 'id')->all();
        }

        return MataPelajaran::query()
            ->whereIn('id', PembagianMengajar::query()
                ->where('gtk_id', $gtkId)
                ->where('tahun_ajaran_id', TahunAjaran::query()->where('is_aktif', true)->value('id') ?? 0)
                ->select('mata_pelajaran_id'))
            ->orderBy('urutan')
            ->pluck('nama', 'id')
            ->all();
    }
}
