<?php

namespace App\Filament\Bendahara\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;

abstract class HalamanKeuangan extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->bolehKelolaKeuangan();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
