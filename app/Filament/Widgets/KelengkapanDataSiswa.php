<?php

namespace App\Filament\Widgets;

use App\Enums\StatusSiswa;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Siswa;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active students whose Buku Induk record still misses data the forms and letters need.
 */
class KelengkapanDataSiswa extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Required data and its label, checked by {@see self::kekurangan()}.
     *
     * @var array<string, string>
     */
    public const array DATA_WAJIB = [
        'nisn' => 'NISN',
        'foto' => 'Foto',
        'tempat_lahir' => 'Tempat lahir',
        'tanggal_lahir' => 'Tanggal lahir',
        'alamat' => 'Alamat',
        'ayah' => 'Data ayah',
        'ibu' => 'Data ibu',
    ];

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Siswa::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Data Siswa Belum Lengkap')
            ->description('Siswa aktif yang biodatanya masih kosong untuk kebutuhan buku induk dan surat.')
            ->query(fn (): Builder => static::querySiswaBelumLengkap()->with(['kelas', 'ayah', 'ibu']))
            ->defaultSort('nama_lengkap')
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->recordUrl(fn (Siswa $record): ?string => SiswaResource::canEdit($record) ? SiswaResource::getUrl('edit', ['record' => $record]) : null)
            ->emptyStateHeading('Semua data siswa aktif sudah lengkap')
            ->columns([
                TextColumn::make('nis')
                    ->label('NIS'),
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('kekurangan')
                    ->label('Belum Diisi')
                    ->state(fn (Siswa $record): array => static::kekurangan($record))
                    ->badge()
                    ->color('warning'),
            ]);
    }

    /**
     * @return Builder<Siswa>
     */
    public static function querySiswaBelumLengkap(): Builder
    {
        return Siswa::query()
            ->where('status', StatusSiswa::Aktif)
            ->where(fn (Builder $query) => $query
                ->whereNull('nisn')
                ->orWhereNull('foto')
                ->orWhereNull('tempat_lahir')
                ->orWhereNull('tanggal_lahir')
                ->orWhereNull('alamat')
                ->orWhereDoesntHave('ayah')
                ->orWhereDoesntHave('ibu'));
    }

    /**
     * @return list<string>
     */
    public static function kekurangan(Siswa $siswa): array
    {
        return array_values(array_filter(
            static::DATA_WAJIB,
            fn (string $label, string $kolom): bool => in_array($kolom, ['ayah', 'ibu'], true)
                ? $siswa->getRelationValue($kolom) === null
                : blank($siswa->getAttribute($kolom)),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
