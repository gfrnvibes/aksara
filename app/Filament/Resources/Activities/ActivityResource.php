<?php

namespace App\Filament\Resources\Activities;

use BokshornIt\FilamentActivityTimeline\Resources\ActivityResource as BaseActivityResource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActivityResource extends BaseActivityResource
{
    public static function table(Table $table): Table
    {
        $table = parent::table($table)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);

        // Compare timestamps directly so the created_at index can be used.
        $table->getFilter('created_at')?->query(
            fn (Builder $query, array $data): Builder => $query
                ->when(
                    filled($data['created_from'] ?? null),
                    fn (Builder $query): Builder => $query->where('created_at', '>=', Carbon::parse($data['created_from'])->startOfDay()),
                )
                ->when(
                    filled($data['created_until'] ?? null),
                    fn (Builder $query): Builder => $query->where('created_at', '<', Carbon::parse($data['created_until'])->addDay()->startOfDay()),
                ),
        );

        return $table;
    }
}
