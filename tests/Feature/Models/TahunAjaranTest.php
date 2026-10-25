<?php

use App\Models\TahunAjaran;

it('deactivates the previous academic year when another one becomes active', function () {
    $lama = TahunAjaran::factory()->aktif()->create();
    $baru = TahunAjaran::factory()->aktif()->create();

    expect($lama->fresh()->is_aktif)->toBeFalse()
        ->and($baru->fresh()->is_aktif)->toBeTrue();
});
