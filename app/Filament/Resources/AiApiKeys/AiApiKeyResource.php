<?php

namespace App\Filament\Resources\AiApiKeys;

use App\Filament\Resources\AiApiKeys\Pages\ManageAiApiKeys;
use App\Models\AiApiKey;
use App\Services\Gemini\KlienGemini;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class AiApiKeyResource extends Resource
{
    protected static ?string $model = AiApiKey::class;

    protected static ?string $slug = 'api-ai';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'AI API Key';

    protected static ?string $pluralModelLabel = 'AI API Key';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Konfigurasi')
                            ->placeholder('Contoh: Gemini Utama')
                            ->required()
                            ->maxLength(100),
                        Select::make('provider')
                            ->label('Provider')
                            ->options([
                                'gemini' => 'Google Gemini',
                            ])
                            ->default('gemini')
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
                TextInput::make('api_key')
                    ->label('API Key')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Disimpan terenkripsi. Saat mengubah data, kosongkan jika tidak ingin mengganti API key.'),
                Textarea::make('daftar_model')
                    ->label('Daftar Model (urut prioritas)')
                    ->rows(2)
                    ->default(fn (): string => (string) config('services.gemini.model', 'gemini-2.5-flash-lite,gemini-2.5-flash'))
                    ->helperText('Pisahkan dengan koma. Sistem akan mencoba model secara berurutan jika terjadi batas kuota atau gangguan.')
                    ->required(),
                Grid::make(3)
                    ->schema([
                        TextInput::make('prioritas')
                            ->label('Prioritas')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Angka kecil dipakai lebih dulu.')
                            ->required(),
                        TextInput::make('timeout_detik')
                            ->label('Timeout (detik)')
                            ->numeric()
                            ->integer()
                            ->minValue(5)
                            ->maxValue(180)
                            ->default((int) config('services.gemini.timeout', 15))
                            ->required(),
                        Toggle::make('is_aktif')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('prioritas')
            ->columns([
                TextColumn::make('prioritas')
                    ->label('#')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Konfigurasi')
                    ->description(fn (AiApiKey $record): string => strtoupper($record->provider).' · '.$record->kunciTersamar())
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('daftar_model')
                    ->label('Model')
                    ->wrap()
                    ->limit(55),
                TextColumn::make('total_permintaan')
                    ->label('Dipakai')
                    ->numeric()
                    ->suffix('×')
                    ->alignCenter(),
                TextColumn::make('status_terakhir')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => 'Normal',
                        'error' => 'Gagal',
                        default => 'Belum diuji',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (AiApiKey $record): ?string => $record->pesan_error_terakhir),
                TextColumn::make('terakhir_dipakai_at')
                    ->label('Terakhir Dipakai')
                    ->since()
                    ->placeholder('Belum pernah')
                    ->toggleable(),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif'),
            ])
            ->recordActions([
                Action::make('uji')
                    ->label('Uji')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->action(function (AiApiKey $record): void {
                        $hasil = app(KlienGemini::class)->ujiKoneksi($record);

                        Notification::make()
                            ->title($hasil['ok'] ? 'Koneksi API AI berhasil' : 'Koneksi API AI gagal')
                            ->body($hasil['pesan'])
                            ->status($hasil['ok'] ? 'success' : 'danger')
                            ->send();
                    }),
                EditAction::make()
                    ->modalWidth('xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Belum ada API key AI tersimpan')
            ->emptyStateDescription('Tambahkan API key Gemini agar fitur pembuat soal AI, ringkasan, dan asisten penulisan berjalan dengan failover otomatis.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiApiKeys::route('/'),
        ];
    }
}
