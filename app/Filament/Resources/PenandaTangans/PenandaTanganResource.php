<?php

namespace App\Filament\Resources\PenandaTangans;

use App\Filament\Resources\PenandaTangans\Pages\ManagePenandaTangans;
use App\Models\PenandaTangan;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PenandaTanganResource extends Resource
{
    protected static ?string $model = PenandaTangan::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 5;

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
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Lengkap & Gelar')
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
                    ->maxLength(30),
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
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
