<?php

namespace App\Filament\Resources\Ppdbs;

use App\Filament\Resources\Ppdbs\Pages\ListPpdbs;
use App\Models\Ppdb;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PpdbResource extends Resource
{
    protected static ?string $model = Ppdb::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $modelLabel = 'PPDB';

    protected static ?string $pluralModelLabel = 'PPDB';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_lengkap')
                ->label('Nama Lengkap')
                ->required()
                ->maxLength(255),
            TextInput::make('nisn')
                ->label('NISN')
                ->required()
                ->maxLength(30),
            TextInput::make('nik')
                ->label('NIK')
                ->required()
                ->maxLength(16)
                ->numeric()
                ->rules(['digits:16']),
            TextInput::make('tempat_lahir')
                ->label('Tempat Lahir')
                ->required()
                ->maxLength(255),
            DatePicker::make('tanggal_lahir')
                ->label('Tanggal Lahir')
                ->required(),
            Radio::make('jenis_kelamin')
                ->label('Jenis Kelamin')
                ->options([
                    'Laki-laki' => 'Laki-laki',
                    'Perempuan' => 'Perempuan',
                ])
                ->required(),
            Textarea::make('alamat')
                ->label('Alamat')
                ->required()
                ->rows(3),
            TextInput::make('nomor_telepon')
                ->label('No Telepon')
                ->required()
                ->maxLength(30),
            TextInput::make('nomor_kip_pip')
                ->label('Nomor KIP/PIP')
                ->nullable()
                ->maxLength(100),
            TextInput::make('nama_ayah_kandung')
                ->label('Nama Ayah Kandung')
                ->required()
                ->maxLength(255),
            TextInput::make('nama_ibu_kandung')
                ->label('Nama Ibu Kandung')
                ->required()
                ->maxLength(255),
            TextInput::make('nama_wali')
                ->label('Nama Wali')
                ->nullable()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nisn')
                    ->searchable(),
                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable(),
                TextColumn::make('tempat_lahir')
                    ->label('Tempat Lahir'),
                TextColumn::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->date(),
                TextColumn::make('jenis_kelamin'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPpdbs::route('/'),
        ];
    }
}
