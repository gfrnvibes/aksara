<?php

use App\Filament\Resources\Siswas\Schemas\SiswaForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

it('adds a numeric-only 16-digit NIK field to student forms', function () {
    $form = SiswaForm::configure(Schema::make([]));
    $nikField = collect($form->getComponents())
        ->flatten(1)
        ->first(fn ($component) => $component instanceof TextInput && $component->getName() === 'nik');

    expect($nikField)->not->toBeNull()
        ->and($nikField->getType())->toBe('text')
        ->and($nikField->getMaxLength())->toBe(16)
        ->and($nikField->getInputMode())->toBe('numeric');
});
