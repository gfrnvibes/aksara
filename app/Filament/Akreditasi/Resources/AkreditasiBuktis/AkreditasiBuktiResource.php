<?php

namespace App\Filament\Akreditasi\Resources\AkreditasiBuktis;

use App\Filament\Akreditasi\Resources\AkreditasiBuktis\Pages\ManageAkreditasiBuktis;
use App\Models\AkreditasiBukti;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PetaBanPdm;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AkreditasiBuktiResource extends Resource
{
    protected static ?string $model = AkreditasiBukti::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Bukti Dukung';

    protected static ?string $pluralModelLabel = 'Bank Bukti 14 Butir';

    protected static ?string $slug = 'bukti';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    /**
     * @return array<string, string>
     */
    public static function opsiButir(): array
    {
        return collect(PetaBanPdm::butir14())
            ->mapWithKeys(fn (array $b): array => [$b['kode'] => "Butir {$b['nomor']}: {$b['judul']}"])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
                Select::make('kode_butir')
                    ->label('Butir BAN-PDM')
                    ->options(static::opsiButir())
                    ->searchable()
                    ->required(),
                TextInput::make('judul')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('berkas_path')
                    ->label('Unggah Berkas Bukti (PDF / Foto)')
                    ->disk('local')
                    ->directory('akreditasi-bukti')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(2048),
                Textarea::make('keterangan')
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode_butir')
            ->columns([
                TextColumn::make('kode_butir')
                    ->label('Butir')
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state)))
                    ->badge()
                    ->sortable(),
                TextColumn::make('judul')
                    ->searchable()
                    ->wrap()
                    ->weight('bold'),
                TextColumn::make('sumber_tipe')
                    ->label('Sumber')
                    ->badge()
                    ->icon(Heroicon::OutlinedLink)
                    ->color('gray'),
                TextColumn::make('berkas_path')
                    ->label('Berkas')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? basename($state) : '-')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('kode_butir')
                    ->label('Butir BAN-PDM')
                    ->options(static::opsiButir()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAkreditasiBuktis::route('/'),
        ];
    }
}
