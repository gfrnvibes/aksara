<?php

namespace App\Filament\Akreditasi\Concerns;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

trait AksesPanelAkreditasi
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getPanel('akreditasi'));
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }
}
