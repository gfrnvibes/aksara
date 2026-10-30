<?php

use App\Filament\Resources\Siswas\Pages\CreateSiswa;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('adds a numeric-only 16-digit NIK field to student forms', function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'Create:Siswa']));

    Livewire::test(CreateSiswa::class)
        ->assertFormFieldExists('nik', fn (TextInput $field): bool => $field->isRequired()
            && $field->getMaxLength() === 16
            && $field->getInputMode() === 'numeric');
});
