<?php

namespace App\Filament\Resources\Ppdbs;

use App\Enums\StatusPpdb;
use App\Filament\Resources\Ppdbs\Pages\ListPpdbs;
use App\Filament\Resources\Ppdbs\Schemas\PpdbForm;
use App\Filament\Resources\Ppdbs\Tables\PpdbsTable;
use App\Models\Ppdb;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PpdbResource extends Resource
{
    protected static ?string $model = Ppdb::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $navigationLabel = 'Pendaftar';

    protected static ?string $modelLabel = 'Pendaftar PPDB';

    protected static ?string $pluralModelLabel = 'Pendaftar PPDB';

    protected static ?string $slug = 'ppdb';

    protected static ?string $recordTitleAttribute = 'nama_lengkap';

    public static function form(Schema $schema): Schema
    {
        return PpdbForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PpdbsTable::configure($table);
    }

    /**
     * Registrations still waiting for the committee's check.
     */
    public static function getNavigationBadge(): ?string
    {
        $jumlah = Ppdb::query()->where('status', StatusPpdb::Menunggu)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu verifikasi';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPpdbs::route('/'),
        ];
    }
}
