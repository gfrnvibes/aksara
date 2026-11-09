<?php

namespace App\Filament\Resources\Gtks;

use App\Enums\JenisGtk;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\Gtks\Pages\CreateGtk;
use App\Filament\Resources\Gtks\Pages\EditGtk;
use App\Filament\Resources\Gtks\Pages\ManageGtks;
use App\Models\Gtk;
use App\Services\Kepegawaian\AkunGtk;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use UnitEnum;

class GtkResource extends Resource
{
    protected static ?string $model = Gtk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'GTK';

    protected static ?string $pluralModelLabel = 'Data GTK';

    protected static ?string $slug = 'gtk';

    protected static ?string $recordTitleAttribute = 'nama';

    /**
     * @var list<string>
     */
    public const array PENDIDIKAN = ['SMA/MA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Data GTK')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Identitas GTK')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Identitas')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('nama')
                                            ->label('Nama Lengkap dengan Gelar')
                                            ->placeholder('Siti Aminah, S.Pd.')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Select::make('jenis_kelamin')
                                            ->options(JenisKelamin::class)
                                            ->required(),
                                        // Kept as text: numeric() would turn the 16 digits into a float such as 3.2E+15.
                                        TextInput::make('nik')
                                            ->label('NIK')
                                            ->inputMode('numeric')
                                            ->extraInputAttributes(['pattern' => '[0-9]*'])
                                            ->rule('digits:16')
                                            ->validationMessages(['digits' => 'NIK harus 16 digit angka.']),
                                        TextInput::make('tempat_lahir')
                                            ->maxLength(255),
                                        DatePicker::make('tanggal_lahir'),
                                    ]),
                            ]),
                        Tab::make('Kepegawaian')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Section::make('Kepegawaian')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('jenis_gtk')
                                            ->label('Jenis GTK')
                                            ->options(JenisGtk::class)
                                            ->default(JenisGtk::Guru)
                                            ->required(),
                                        Select::make('status_kepegawaian')
                                            ->options(StatusKepegawaian::class)
                                            ->required(),
                                        TextInput::make('nip')
                                            ->label('NIP')
                                            ->maxLength(30)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('nuptk')
                                            ->label('NUPTK')
                                            ->maxLength(30)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('peg_id')
                                            ->label('PegID')
                                            ->maxLength(30)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('jabatan')
                                            ->label('Jabatan / Tugas Tambahan')
                                            ->placeholder('Guru Mata Pelajaran')
                                            ->maxLength(255),
                                        DatePicker::make('tmt_tugas')
                                            ->label('TMT Tugas'),
                                        TextInput::make('nomor_kode')
                                            ->label('Nomor Kode Jadwal')
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(99)
                                            ->helperText('Nomor guru di kode jadwal pelajaran, misalnya 13 untuk kode 13 A, 13 B. Kosongkan agar dinomori otomatis.')
                                            ->unique(ignoreRecord: true),
                                        Toggle::make('is_sertifikasi')
                                            ->label('Sudah sertifikasi')
                                            ->inline(false),
                                    ]),
                                Toggle::make('is_aktif')
                                    ->label('Aktif')
                                    ->helperText('GTK nonaktif tidak bisa masuk ke panel guru dan tidak ditawarkan untuk pembagian mengajar baru.')
                                    ->default(true),
                            ]),
                        Tab::make('Pendidikan & Kontak')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Pendidikan & Kontak')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('pendidikan_terakhir')
                                            ->options(array_combine(static::PENDIDIKAN, static::PENDIDIKAN)),
                                        TextInput::make('program_studi')
                                            ->maxLength(255),
                                        TextInput::make('nomor_hp')
                                            ->label('No. HP')
                                            ->tel()
                                            ->helperText('Bisa dipakai untuk masuk ke aplikasi.')
                                            ->maxLength(20)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        Textarea::make('alamat')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Akun')
                            ->key('akun', isInheritable: false)
                            ->icon(Heroicon::OutlinedKey)
                            ->schema([
                                Section::make('Akun Login GTK')->schema([
                                    Text::make('Simpan data GTK untuk membuka form akun. Tombol berikut menyimpan seluruh isian dan langsung membuka tab Akun.')
                                        ->visibleOn('create'),
                                    Actions::make([
                                        Action::make('simpanDanAturAkun')->label('Simpan GTK & Atur Akun')->icon(Heroicon::OutlinedKey)
                                            ->visible(fn (): bool => static::canEdit(new Gtk))
                                            ->action(fn (CreateGtk $livewire) => $livewire->simpanDanAturAkun()),
                                    ])->key('simpanUntukAkun')->visibleOn('create'),
                                    Text::make(fn (?Gtk $record): string => $record?->user_id === null ? 'Status akun: belum memiliki akun login.' : 'Status akun: sudah memiliki akun login.')
                                        ->visibleOn('edit'),
                                    Text::make(fn (?Gtk $record): string => $record === null ? '' : 'Identitas login: '.(implode(' / ', AkunGtk::identitasLogin($record)) ?: 'Belum tersedia. Isi NIP, NUPTK, No. HP, atau email terlebih dahulu.'))
                                        ->visibleOn('edit'),
                                    Text::make(fn (?Gtk $record): string => $record?->is_aktif ? 'GTK aktif dapat masuk ke panel guru.' : 'GTK nonaktif tidak dapat masuk ke panel guru.')
                                        ->visibleOn('edit'),
                                    Text::make('Simpan perubahan identitas GTK sebelum mengatur akun. Akun baru atau akun yang direset wajib mengganti password saat masuk pertama kali.')
                                        ->color('gray')->visibleOn('edit'),
                                    TextInput::make('password_akun')->label('Password awal / password baru')->password()->revealable()
                                        ->autocomplete('new-password')->minLength(6)->maxLength(255)->dehydrated(false)
                                        ->helperText('Minimal 6 karakter. Tekan Buat akun login atau Reset password untuk menerapkannya.')
                                        ->visible(fn (?Gtk $record): bool => $record !== null && static::canEdit($record) && ($record->user_id === null || AkunGtk::bolehDireset($record, auth()->user()))),
                                    Actions::make(fn (?Gtk $record): array => $record === null ? [] : [static::aksiAkunDalamForm(static::aksiBuatAkun()), static::aksiAkunDalamForm(static::aksiResetPassword())])
                                        ->key('aksiAkun')->visibleOn('edit'),
                                    Text::make('Akun ini memiliki peran admin. Reset password hanya dapat dilakukan oleh super admin.')
                                        ->color('warning')
                                        ->visible(fn (?Gtk $record): bool => $record !== null && $record->user_id !== null && ! AkunGtk::bolehDireset($record, auth()->user())),
                                ]),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Gtk $record): ?string => $record->jabatan),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('nuptk')
                    ->label('NUPTK')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('jenis_gtk')
                    ->label('Jenis')
                    ->toggleable(),
                TextColumn::make('nomor_kode')
                    ->label('Kode Jadwal')
                    ->formatStateUsing(fn (int $state): string => str_pad((string) $state, 2, '0', STR_PAD_LEFT))
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status_kepegawaian')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('nomor_hp')
                    ->label('No. HP')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('user_id')
                    ->label('Akun')
                    ->boolean()
                    ->getStateUsing(fn (Gtk $record): bool => $record->user_id !== null)
                    ->tooltip(fn (Gtk $record): string => $record->user === null ? 'Belum punya akun login' : 'Masuk dengan: '.implode(' / ', AkunGtk::identitasLogin($record))),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif')
                    ->disabled(fn (Gtk $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->filters([
                SelectFilter::make('jenis_gtk')
                    ->label('Jenis GTK')
                    ->options(JenisGtk::class),
                SelectFilter::make('status_kepegawaian')
                    ->options(StatusKepegawaian::class),
                TernaryFilter::make('akun')
                    ->label('Akun login')
                    ->trueLabel('Sudah punya')
                    ->falseLabel('Belum punya')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                    ),
                TernaryFilter::make('is_aktif')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->default(true),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::aksiBuatAkun(),
                    static::aksiResetPassword(),
                ])
                    ->label('Akun')
                    ->icon(Heroicon::OutlinedKey)
                    ->link(),
                EditAction::make(),
                HapusMasterDataActions::hapus(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::aksiBuatAkunMassal(),
                    HapusMasterDataActions::hapusMassal(),
                ]),
            ]);
    }

    protected static function aksiAkunDalamForm(Action $action): Action
    {
        return $action->schema([])->mutateDataUsing(function (Get $get): array {
            $valid = Validator::make(['data' => ['password_akun' => $get('password_akun')]], [
                'data.password_akun' => ['required', 'string', 'min:6', 'max:255'],
            ])->validate();

            return ['password_awal' => $valid['data']['password_akun']];
        })->after(fn (Set $set) => $set('password_akun', null));
    }

    protected static function fieldPasswordAwal(): TextInput
    {
        return TextInput::make('password_awal')
            ->label('Password awal')
            ->helperText('Sampaikan password ini kepada guru. Guru wajib menggantinya saat pertama kali masuk.')
            ->required()
            ->minLength(6)
            ->maxLength(255);
    }

    protected static function aksiBuatAkun(): Action
    {
        return Action::make('buatAkun')
            ->label('Buat akun login')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Gtk $record): bool => $record->user_id === null && (auth()->user()?->can('update', $record) ?? false))
            ->modalWidth('md')
            ->modalDescription(fn (Gtk $record): string => AkunGtk::punyaIdentitasLogin($record)
                ? 'Guru masuk ke panel guru dengan: '.implode(' / ', AkunGtk::identitasLogin($record)).'.'
                : 'Isi dulu NIP, NUPTK, No. HP, atau email GTK ini supaya ada yang bisa dipakai untuk masuk.')
            ->schema([static::fieldPasswordAwal()])
            ->action(function (array $data, Gtk $record, Action $action): void {
                $record->refresh();
                abort_unless(auth()->user()?->can('update', $record), 403);
                if ($record->user_id !== null) {
                    Notification::make()->warning()->title('GTK sudah memiliki akun login')->send();
                    $action->halt();
                }
                if (! AkunGtk::punyaIdentitasLogin($record)) {
                    Notification::make()->danger()->title('Akun belum bisa dibuat')->body('Isi dulu NIP, NUPTK, No. HP, atau email GTK ini.')->send();

                    $action->halt();
                }

                app(AkunGtk::class)->buat($record, $data['password_awal']);
                $record->refresh();

                Notification::make()->success()->title('Akun login dibuat')->send();
            });
    }

    protected static function aksiResetPassword(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Gtk $record): bool => (auth()->user()?->can('update', $record) ?? false) && AkunGtk::bolehDireset($record, auth()->user()))
            ->modalWidth('md')
            ->modalDescription(fn (Gtk $record): string => 'Masuk dengan: '.implode(' / ', AkunGtk::identitasLogin($record)).'.')
            ->schema([static::fieldPasswordAwal()])
            ->action(function (array $data, Gtk $record): void {
                $record->refresh();
                abort_unless((auth()->user()?->can('update', $record) ?? false) && AkunGtk::bolehDireset($record, auth()->user()), 403);
                app(AkunGtk::class)->resetPassword($record, $data['password_awal']);
                $record->refresh();

                Notification::make()->success()->title('Password direset')->send();
            });
    }

    protected static function aksiBuatAkunMassal(): BulkAction
    {
        return BulkAction::make('buatAkunMassal')
            ->label('Buat akun login')
            ->icon(Heroicon::OutlinedUserPlus)
            ->authorize(fn (): bool => auth()->user()?->can('update', new Gtk) ?? false)
            ->modalWidth('md')
            ->modalDescription('Semua GTK terpilih yang belum punya akun dibuatkan akun dengan password awal yang sama.')
            ->schema([static::fieldPasswordAwal()])
            ->action(function (array $data, Collection $records): void {
                $akun = app(AkunGtk::class);
                $dibuat = 0;
                $tanpaIdentitas = [];

                foreach ($records as $gtk) {
                    /** @var Gtk $gtk */
                    if ($gtk->user_id !== null) {
                        continue;
                    }

                    if (! AkunGtk::punyaIdentitasLogin($gtk)) {
                        $tanpaIdentitas[] = $gtk->nama;

                        continue;
                    }

                    $akun->buat($gtk, $data['password_awal']);
                    $dibuat++;
                }

                $notifikasi = Notification::make()
                    ->success()
                    ->title("{$dibuat} akun login dibuat");

                if ($tanpaIdentitas !== []) {
                    $notifikasi->warning()
                        ->body('Belum dibuat karena NIP, NUPTK, No. HP, dan email kosong: '.implode(', ', $tanpaIdentitas).'.')
                        ->persistent();
                }

                $notifikasi->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGtks::route('/'),
            'create' => CreateGtk::route('/create'),
            'edit' => EditGtk::route('/{record}/edit'),
        ];
    }
}
