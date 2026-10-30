<?php

namespace App\Filament\Resources\SuratKeluars;

use App\Filament\Resources\SuratKeluars\Pages\CreateSuratKeluar;
use App\Filament\Resources\SuratKeluars\Pages\EditSuratKeluar;
use App\Filament\Resources\SuratKeluars\Pages\ListSuratKeluars;
use App\Filament\Resources\SuratKeluars\Schemas\SuratKeluarForm;
use App\Filament\Resources\SuratKeluars\Tables\SuratKeluarsTable;
use App\Models\SuratKeluar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SuratKeluarResource extends Resource
{
    protected static ?string $model = SuratKeluar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Surat Keluar';

    protected static ?string $pluralModelLabel = 'Surat Keluar';

    protected static ?string $slug = 'surat-keluar';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function form(Schema $schema): Schema
    {
        return SuratKeluarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuratKeluarsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratKeluars::route('/'),
            'create' => CreateSuratKeluar::route('/create'),
            'edit' => EditSuratKeluar::route('/{record}/edit'),
        ];
    }
}
