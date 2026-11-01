<?php

namespace App\Filament\Resources\PembagianMengajars;

use App\Filament\Resources\PembagianMengajars\Pages\ManagePembagianMengajars;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class PembagianMengajarResource extends Resource
{
    protected static ?string $model = PembagianMengajar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Pembagian Mengajar';

    protected static ?string $pluralModelLabel = 'Pembagian Mengajar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->live()
                    ->required(),
                Select::make('gtk_id')
                    ->label('Guru')
                    ->relationship('gtk', 'nama', fn (Builder $query, ?PembagianMengajar $record): Builder => $query
                        ->where(fn (Builder $gtk) => $gtk->where('is_aktif', true)->when($record?->gtk_id, fn (Builder $gtk, int $id) => $gtk->orWhereKey($id)))
                        ->orderBy('nama'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('mata_pelajaran_id')
                    ->label('Mata Pelajaran')
                    ->options(fn (): array => MataPelajaran::query()->aktifBerurutan()->pluck('nama', 'id')->all())
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn (?PembagianMengajar $record): array => Kelas::batasiYangTersedia(Kelas::query(), $record?->kelas_id)->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                    ->helperText('Satu mata pelajaran di satu kelas hanya diajar satu guru per tahun ajaran.')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('tahun_ajaran_id', $get('tahun_ajaran_id'))
                            ->where('mata_pelajaran_id', $get('mata_pelajaran_id')),
                    )
                    ->validationMessages(['unique' => 'Mata pelajaran ini di kelas tersebut sudah punya guru pada tahun ajaran ini.'])
                    ->required(),
                TextInput::make('jp_per_minggu')
                    ->label('JP per Minggu')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(2)
                    ->required(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['gtk', 'kelas', 'mataPelajaran', 'tahunAjaran'])
                ->select('pembagian_mengajars.*')
                ->leftJoin('gtks as gtk_urut', 'gtk_urut.id', '=', 'pembagian_mengajars.gtk_id')
                ->leftJoin('kelas as kelas_urut', 'kelas_urut.id', '=', 'pembagian_mengajars.kelas_id'))
            ->groups([
                Group::make('gtk_id')
                    ->label('Guru')
                    ->getTitleFromRecordUsing(fn (PembagianMengajar $record): string => $record->gtk->nama)
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('gtk_urut.nama'))
                    ->collapsible(),
                Group::make('kelas_id')
                    ->label('Kelas')
                    ->getTitleFromRecordUsing(fn (PembagianMengajar $record): string => $record->kelas->nama)
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('kelas_urut.tingkat')->orderBy('kelas_urut.nama'))
                    ->collapsible(),
            ])
            ->defaultGroup('gtk_id')
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('kelas_urut.tingkat')->orderBy('kelas_urut.nama'))
            ->columns([
                TextColumn::make('gtk.nama')
                    ->label('Guru')
                    ->searchable(),
                TextColumn::make('mataPelajaran.nama')
                    ->label('Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                TextColumn::make('jp_per_minggu')
                    ->label('JP / Minggu')
                    ->numeric()
                    ->summarize(Sum::make()->label('Total JP')),
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id')),
                SelectFilter::make('gtk_id')
                    ->label('Guru')
                    ->options(fn (): array => Gtk::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),
                SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn (): array => Kelas::query()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('mata_pelajaran_id')
                    ->label('Mata Pelajaran')
                    ->options(fn (): array => MataPelajaran::query()->aktifBerurutan()->pluck('nama', 'id')->all()),
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
            'index' => ManagePembagianMengajars::route('/'),
        ];
    }
}
