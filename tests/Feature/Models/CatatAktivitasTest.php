<?php

use App\Models\Siswa;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

it('records which user changed which student fields', function () {
    $petugas = User::factory()->create();
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Nama Lama', 'tempat_lahir' => 'Bandung']);

    actingAs($petugas);
    $siswa->update(['nama_lengkap' => 'Nama Baru']);

    $aktivitas = Activity::query()->whereMorphedTo('subject', $siswa)->where('event', 'updated')->sole();

    expect($aktivitas->causer->is($petugas))->toBeTrue()
        ->and($aktivitas->attribute_changes['attributes'])->toBe(['nama_lengkap' => 'Nama Baru'])
        ->and($aktivitas->attribute_changes['old'])->toBe(['nama_lengkap' => 'Nama Lama']);
});

it('never writes passwords or tokens to the activity log', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'rahasia-baru-123', 'name' => 'Nama Baru']);

    $perubahan = Activity::query()->whereMorphedTo('subject', $user)->where('event', 'updated')->sole()->attribute_changes;

    expect($perubahan['attributes'])->toHaveKey('name')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});
