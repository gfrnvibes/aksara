<?php

use App\Models\Ekstrakurikuler;
use App\Models\MataPelajaran;

use function Pest\Laravel\get;

it('lists only the active subjects from the master data', function () {
    MataPelajaran::factory()->create(['nama' => 'Fikih']);
    MataPelajaran::factory()->create(['nama' => 'Bahasa Sunda', 'is_aktif' => false]);
    Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']);

    get(route('home'))
        ->assertOk()
        ->assertSeeText(['Satu mata pelajaran.', 'Fikih', 'Pramuka'])
        ->assertDontSeeText('Bahasa Sunda');
});

it('shows contact details only when the madrasah has filled them in', function () {
    config(['sekolah.telepon' => null, 'sekolah.whatsapp' => null]);

    get(route('home'))
        ->assertOk()
        ->assertDontSeeText('Telepon')
        ->assertDontSee('wa.me/62', escape: false);

    config(['sekolah.telepon' => '(0262) 123456', 'sekolah.whatsapp' => '+62 812-3456-7890']);

    get(route('home'))
        ->assertSeeText('(0262) 123456')
        ->assertSee('https://wa.me/6281234567890', escape: false);
});
