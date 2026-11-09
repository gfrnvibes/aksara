<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Siswa;
use BokshornIt\FilamentActivityTimeline\Resources\Pages\ListActivities;
use BokshornIt\FilamentActivityTimeline\Resources\Pages\ViewActivity;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(userWithPermissions(['ViewAny:Activity', 'View:Activity'])->assignRole(Role::findOrCreate('operator', 'web')));
});

it('paginates the activity list without offering an unbounded all option', function () {
    Activity::query()->insert(array_fill(0, 30, [
        'description' => 'Aktivitas contoh', 'created_at' => now(), 'updated_at' => now(),
    ]));

    $page = Livewire::test(ListActivities::class)->assertOk();
    expect($page->instance()->getTableRecords()->count())->toBe(25)
        ->and($page->instance()->getTable()->getPaginationPageOptions())->toBe([25, 50, 100])
        ->and(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();
    $this->get(ActivityResource::getUrl())->assertOk();
});

it('filters an entire date including late evening and excludes the next day', function () {
    $inside = Activity::query()->create(['description' => 'Dalam periode', 'created_at' => '2026-10-10 23:59:59']);
    $outside = Activity::query()->create(['description' => 'Hari berikutnya', 'created_at' => '2026-10-11 00:00:00']);
    $before = Activity::query()->create(['description' => 'Hari sebelumnya', 'created_at' => '2026-10-09 23:59:59']);

    Livewire::test(ListActivities::class)
        ->filterTable('created_at', ['created_from' => '2026-10-10', 'created_until' => '2026-10-10'])
        ->assertCanSeeTableRecords([$inside])
        ->assertCanNotSeeTableRecords([$outside, $before]);
});

it('keeps the full audit changes accessible on the detail page', function () {
    $student = Siswa::factory()->create(['nama_lengkap' => 'Nama Sebelumnya']);
    $student->update(['nama_lengkap' => 'Nama Sesudahnya']);
    $activity = Activity::query()->whereMorphedTo('subject', $student)->where('event', 'updated')->sole();

    Livewire::test(ViewActivity::class, ['record' => $activity->getRouteKey()])
        ->assertOk()->assertSee('Nama Sebelumnya')->assertSee('Nama Sesudahnya');
});
