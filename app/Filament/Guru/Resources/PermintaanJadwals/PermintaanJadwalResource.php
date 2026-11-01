<?php

namespace App\Filament\Guru\Resources\PermintaanJadwals;

use App\Filament\Guru\Resources\PermintaanJadwals\Pages\ManagePermintaanJadwalSaya;
use App\Filament\Resources\PermintaanJadwals\Schemas\PermintaanJadwalForm;
use App\Filament\Resources\PermintaanJadwals\Tables\PermintaanJadwalsTable;
use App\Models\Gtk;
use App\Models\PermintaanJadwal;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The teacher's own scheduling wishes. Access follows the teacher's GTK record rather than Shield permissions.
 */
class PermintaanJadwalResource extends Resource
{
    protected static ?string $model = PermintaanJadwal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Permintaan Jadwal';

    protected static ?string $pluralModelLabel = 'Permintaan Jadwal Saya';

    protected static ?string $slug = 'permintaan-jadwal';

    public static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('gtk_id', static::gtk()->id ?? 0);
    }

    /**
     * A teacher may do anything with their own wishes and nothing with anyone else's.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $gtk = static::gtk();

        return $gtk !== null && (! $record instanceof PermintaanJadwal || $record->gtk_id === $gtk->id)
            ? Response::allow()
            : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return PermintaanJadwalForm::configure($schema, static::gtk(...));
    }

    public static function table(Table $table): Table
    {
        return PermintaanJadwalsTable::configure($table, kelompokkanPerGuru: false)
            ->description('Isi hanya jika ada jam yang tidak bisa atau ingin Anda ajar. Tanpa permintaan, Anda dianggap siap mengajar kapan saja.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermintaanJadwalSaya::route('/'),
        ];
    }
}
