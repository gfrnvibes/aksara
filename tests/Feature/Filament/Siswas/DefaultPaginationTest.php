<?php

use App\Filament\Resources\Siswas\Pages\ListSiswas;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('uses 25 records per page by default on the student list', function () {
    actingAs(userWithPermissions(['ViewAny:Siswa']));

    Livewire::test(ListSiswas::class)
        ->assertSet('tableRecordsPerPage', 25);
});
