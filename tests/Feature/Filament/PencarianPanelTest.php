<?php

use App\Filament\GlobalSearch\PencarianPanel;
use App\Models\Gtk;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('finds treasurer menu links within the current panel', function () {
    Role::findOrCreate('bendahara', 'web');
    actingAs(User::factory()->create()->assignRole('bendahara'));
    Filament::setCurrentPanel('bendahara');
    $results = app(PencarianPanel::class)->getResults('tagihan')->getCategories()->get('Menu Panel');
    expect($results)->toHaveCount(1)
        ->and($results[0]->title)->toBe('Tagihan Siswa')
        ->and($results[0]->url)->toContain('/bendahara/tagihan-siswa');
    $this->get('/bendahara')->assertOk()->assertSee('fi-sidebar')->assertSee('fi-global-search')->assertSee('fi-user-menu');
});

it('finds teacher pages without offering restricted homeroom pages', function () {
    $guru = Gtk::factory()->denganAkun()->create();
    actingAs($guru->user);
    Filament::setCurrentPanel('guru');
    $results = app(PencarianPanel::class)->getResults('jadwal')->getCategories()->get('Menu Panel');
    expect(collect($results)->pluck('title')->all())->toContain('Jadwal Saya');
    expect(app(PencarianPanel::class)->getResults('rapor')->getCategories()->get('Menu Panel', []))->toBeEmpty();
    $this->get('/guru')->assertOk()->assertSee('fi-global-search')->assertSee('fi-user-menu');
});

it('returns no panel search results to users without panel access', function () {
    actingAs(User::factory()->create());
    Filament::setCurrentPanel('bendahara');
    expect(app(PencarianPanel::class)->getResults('tagihan')->getCategories())->toBeEmpty();
});
