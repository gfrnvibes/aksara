<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Gtk;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('gtk_id')
                    ->label('Ambil dari GTK')
                    ->placeholder('Pilih GTK (opsional)...')
                    ->helperText('Memilih GTK akan mengisi nama otomatis dan menghubungkan akun ke data GTK tersebut.')
                    ->options(fn (?User $record): array => Gtk::query()
                        ->where(fn ($query) => $query
                            ->whereNull('user_id')
                            ->when($record?->gtk !== null, fn ($q) => $q->orWhere('id', $record->gtk->id)))
                        ->orderBy('nama')
                        ->get()
                        ->mapWithKeys(fn (Gtk $gtk): array => [$gtk->id => $gtk->nama.($gtk->nip ? ' (NIP: '.$gtk->nip.')' : '')])
                        ->all())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state($record?->gtk?->id))
                    ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
                        if ($state === null) {
                            return;
                        }

                        $gtk = Gtk::query()->find($state);
                        if ($gtk === null) {
                            return;
                        }

                        $set('name', $gtk->nama);

                        if (filled($gtk->email) && blank($get('email')) && ! User::query()->where('email', $gtk->email)->exists()) {
                            $set('email', $gtk->email);
                        }
                    }),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required(fn (?User $record, Get $get): bool => $record?->gtk === null && blank($get('gtk_id')))
                    ->helperText('Boleh kosong untuk akun guru, yang masuk dengan NIP, NUPTK, atau No. HP.')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan jika tidak ingin mengubah password.' : null)
                    ->minLength(8)
                    ->maxLength(255),
                Select::make('roles')
                    ->label('Peran')
                    ->helperText('Tambahkan peran bendahara pada akun guru yang merangkap bendahara. Panel Guru tetap dapat digunakan.')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('gtk.nama')
                    ->label('GTK')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (User $record, EditAction $action) => static::hubungkanGtk($record, $action->getRawData()['gtk_id'] ?? null)),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function hubungkanGtk(User $record, mixed $gtkId): void
    {
        $gtkId = filled($gtkId) ? (int) $gtkId : null;

        if ($record->gtk !== null && $record->gtk->id !== $gtkId) {
            $record->gtk->forceFill(['user_id' => null])->save();
        }

        if ($gtkId !== null) {
            $gtk = Gtk::query()
                ->whereKey($gtkId)
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $record->id))
                ->first();

            $gtk?->user()->associate($record)->save();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
