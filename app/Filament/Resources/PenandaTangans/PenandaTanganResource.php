<?php

namespace App\Filament\Resources\PenandaTangans;

use App\Filament\Resources\PenandaTangans\Pages\ManagePenandaTangans;
use App\Models\Gtk;
use App\Models\PenandaTangan;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PenandaTanganResource extends Resource
{
    protected static ?string $model = PenandaTangan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Penanda Tangan';

    protected static ?string $pluralModelLabel = 'Penanda Tangan Surat';

    protected static ?string $recordTitleAttribute = 'nama';

    /**
     * Common titles offered while typing the official's title. The principal is not one of them: they sign every
     * letter from the academic year's data.
     *
     * @var list<string>
     */
    public const array JABATAN_UMUM = [
        'Wakil Kepala Madrasah Bidang Kurikulum',
        'Wakil Kepala Madrasah Bidang Kesiswaan',
        'Wakil Kepala Madrasah Bidang Sarana Prasarana',
        'Wakil Kepala Madrasah Bidang Humas',
        'Kepala Tata Usaha',
        'Ketua Panitia',
        'Bendahara',
        'Ketua Yayasan',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::isianUtama(),
                TextInput::make('urutan')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Urutan dalam pilihan penanda tangan; angka kecil di atas.'),
                Toggle::make('is_aktif')
                    ->label('Aktif')
                    ->helperText('Penanda tangan nonaktif tidak bisa dipilih untuk surat baru; surat lama tetap memakai namanya.')
                    ->default(true),
            ])
            ->columns(1);
    }

    /**
     * Who signs and under what title, shared with the form that adds a signer while writing a letter.
     *
     * @return list<Component>
     */
    public static function isianUtama(): array
    {
        return [
            Select::make('gtk_id')
                ->label('Guru / Tenaga Kependidikan')
                ->options(fn (Get $get): array => Gtk::query()
                    ->where(fn (Builder $query) => $query->where('is_aktif', true)->when($get('gtk_id'), fn (Builder $query, int|string $id) => $query->orWhereKey($id)))
                    ->orderBy('nama')
                    ->pluck('nama', 'id')
                    ->all())
                ->searchable()
                ->placeholder('Bukan GTK (staf yayasan atau pihak luar)')
                ->helperText('Nama dan NIP mengikuti Data GTK. Kosongkan untuk staf yayasan atau pihak luar. Guru dengan beberapa jabatan dibuat satu baris per jabatan.')
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $gtk = Gtk::query()->whereKey($get('gtk_id'))->first();

                    if ($gtk !== null) {
                        $set('nama', $gtk->nama);
                        $set('nip', $gtk->nip);
                    }
                }),
            TextInput::make('nama')
                ->label('Nama Lengkap & Gelar')
                ->readOnly(fn (Get $get): bool => filled($get('gtk_id')))
                ->required()
                ->maxLength(255),
            TextInput::make('jabatan')
                ->datalist(static::JABATAN_UMUM)
                ->helperText('Tertulis di atas tanda tangan. Kepala madrasah tidak perlu ditambahkan: namanya diambil dari menu Tahun Ajaran.')
                ->required()
                ->maxLength(255)
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (PenandaTangan::adalahJabatanKepala((string) $value)) {
                        $fail('Kepala madrasah sudah otomatis menandatangani dari data Tahun Ajaran; pilih jabatan lain.');
                    }
                }]),
            TextInput::make('nip')
                ->label('NIP')
                ->readOnly(fn (Get $get): bool => filled($get('gtk_id')))
                ->maxLength(30),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PenandaTangan $record): string => $record->gtk_id === null ? 'Bukan GTK' : 'GTK'),
                TextColumn::make('jabatan')
                    ->searchable(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->placeholder('-'),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Surat yang sudah dibuat tetap memakai nama dan jabatan penanda tangan ini.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePenandaTangans::route('/'),
        ];
    }
}
