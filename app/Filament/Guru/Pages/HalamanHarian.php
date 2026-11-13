<?php

namespace App\Filament\Guru\Pages;

use App\Models\User;
use App\Services\Harian\AksesHarian;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

abstract class HalamanHarian extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->gtk?->is_aktif || AksesHarian::pengelola($user));
    }
}
