<?php

namespace App\Filament\Resources\Pengumumans;

use App\Filament\Resources\Pengumumans\Pages\ManagePengumumans;
use App\Models\Pengumuman;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class PengumumanResource extends Resource
{
    protected static ?string $model = Pengumuman::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Informasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static ?string $slug = 'pengumuman';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('judul')
                    ->label('Judul Pengumuman')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('tujuan')
                    ->label('Tujuan Penerima')
                    ->options([
                        'guru' => 'Guru / GTK',
                        'siswa' => 'Siswa / Orang Tua',
                        'semua' => 'Keduanya (Guru & Siswa)',
                    ])
                    ->default('semua')
                    ->required(),
                Toggle::make('is_aktif')
                    ->label('Aktif Ditampilkan')
                    ->default(true)
                    ->inline(false),
                DatePicker::make('tanggal_mulai')
                    ->label('Mulai Tampil (Opsional)'),
                DatePicker::make('tanggal_selesai')
                    ->label('Selesai Tampil (Opsional)')
                    ->afterOrEqual('tanggal_mulai'),
                Textarea::make('isi')
                    ->label('Isi Pengumuman')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Pengumuman::TUJUAN[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'guru' => 'info',
                        'siswa' => 'warning',
                        default => 'success',
                    }),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('tanggal_mulai')
                    ->label('Mulai')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                TextColumn::make('tanggal_selesai')
                    ->label('Selesai')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                TextColumn::make('pembuat.name')
                    ->label('Pembuat')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tujuan')
                    ->label('Tujuan')
                    ->options(Pengumuman::TUJUAN),
                TernaryFilter::make('is_aktif')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePengumumans::route('/'),
        ];
    }
}
