<?php

namespace App\Filament\Resources\PermintaanJadwals;

use App\Filament\Resources\PermintaanJadwals\Pages\ManagePermintaanJadwals;
use App\Filament\Resources\PermintaanJadwals\Schemas\PermintaanJadwalForm;
use App\Filament\Resources\PermintaanJadwals\Tables\PermintaanJadwalsTable;
use App\Models\PermintaanJadwal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every teacher's scheduling wishes, filled in by the curriculum staff or an operator on a teacher's behalf.
 */
class PermintaanJadwalResource extends Resource
{
    protected static ?string $model = PermintaanJadwal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Permintaan Jadwal';

    protected static ?string $pluralModelLabel = 'Permintaan Jadwal Guru';

    public static function form(Schema $schema): Schema
    {
        return PermintaanJadwalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermintaanJadwalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermintaanJadwals::route('/'),
        ];
    }
}
