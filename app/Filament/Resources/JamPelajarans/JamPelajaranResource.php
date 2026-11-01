<?php

namespace App\Filament\Resources\JamPelajarans;

use App\Enums\Hari;
use App\Enums\JenisJamPelajaran;
use App\Filament\Resources\JamPelajarans\Pages\ManageJamPelajarans;
use App\Models\JamPelajaran;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class JamPelajaranResource extends Resource
{
    protected static ?string $model = JamPelajaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Jam Pelajaran';

    protected static ?string $pluralModelLabel = 'Jam Pelajaran';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('hari')
                    ->options(Hari::class)
                    ->required(),
                Select::make('jenis')
                    ->options(JenisJamPelajaran::class)
                    ->default(JenisJamPelajaran::Pelajaran)
                    ->live()
                    ->required(),
                TimePicker::make('mulai')
                    ->seconds(false)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('hari', $get('hari') instanceof Hari ? $get('hari')->value : $get('hari')),
                    )
                    ->validationMessages(['unique' => 'Sudah ada jam yang mulai pada waktu ini di hari tersebut.'])
                    ->required(),
                TimePicker::make('selesai')
                    ->seconds(false)
                    ->after('mulai')
                    ->required(),
                TextInput::make('keterangan')
                    ->placeholder('Shalat Dzuhur')
                    ->helperText('Ditampilkan di jadwal untuk istirahat dan kegiatan.')
                    ->visible(fn (Get $get): bool => ($get('jenis') instanceof JenisJamPelajaran ? $get('jenis') : JenisJamPelajaran::tryFrom((string) $get('jenis'))) !== JenisJamPelajaran::Pelajaran)
                    ->maxLength(255),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('hari')
                    ->getTitleFromRecordUsing(fn (JamPelajaran $record): string => $record->hari->getLabel())
                    ->collapsible(),
            ])
            ->defaultGroup('hari')
            ->defaultSort('mulai')
            ->paginated(false)
            ->columns([
                TextColumn::make('waktu')
                    ->label('Waktu')
                    ->state(fn (JamPelajaran $record): string => $record->rentang())
                    ->fontFamily('mono'),
                TextColumn::make('label')
                    ->label('Jam')
                    ->state(fn (JamPelajaran $record): string => $record->label())
                    ->badge()
                    ->color(fn (JamPelajaran $record): string => $record->jenis->getColor()),
                TextColumn::make('durasi')
                    ->label('Durasi')
                    ->state(fn (JamPelajaran $record): string => (int) round((strtotime($record->selesai) - strtotime($record->mulai)) / 60).' menit'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJamPelajarans::route('/'),
        ];
    }
}
