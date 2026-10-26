<?php

namespace App\Filament\Resources\Rapors;

use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Filament\Resources\Rapors\Pages\EditRapor;
use App\Filament\Resources\Rapors\Pages\ListRapors;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Filament\Resources\Rapors\Tables\RaporsTable;
use App\Models\Rapor;
use App\Models\Siswa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RaporResource extends Resource
{
    protected static ?string $model = Rapor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Buku Induk';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Nilai Rapor';

    protected static ?string $pluralModelLabel = 'Data Nilai Siswa';

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Rapor) {
            return static::getModelLabel();
        }

        return "{$record->siswa->nama_lengkap} - {$record->tahunAjaran->nama} {$record->semester->getLabel()}";
    }

    /**
     * Report cards of students in the trash stay hidden until the student is restored.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('siswa_id', Siswa::query()->select('id'));
    }

    /**
     * Let the edit page open report cards in the trash, so they can be restored or deleted for good.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return RaporForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RaporsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRapors::route('/'),
            'create' => CreateRapor::route('/create'),
            'edit' => EditRapor::route('/{record}/edit'),
        ];
    }
}
