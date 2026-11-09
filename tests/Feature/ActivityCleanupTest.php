<?php

use App\Actions\BersihkanActivityLog;
use Spatie\Activitylog\Models\Activity;

it('cleans more than one batch while keeping logs within the retention period', function () {
    $this->travelTo(now()->startOfSecond());
    Activity::query()->insert(array_fill(0, 1001, [
        'log_name' => 'default', 'description' => 'Log lama',
        'created_at' => now()->subDays(366), 'updated_at' => now()->subDays(366),
    ]));
    $recent = Activity::query()->create(['description' => 'Log baru', 'created_at' => now()]);
    $boundary = Activity::query()->create(['description' => 'Batas retensi', 'created_at' => now()->subDays(365)]);

    $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

    expect(Activity::query()->count())->toBe(2)
        ->and($recent->fresh())->not->toBeNull()
        ->and($boundary->fresh())->not->toBeNull();
});

it('preserves other log names when cleaning only one log', function () {
    Activity::query()->insert([
        ['log_name' => 'audit', 'description' => 'Audit', 'created_at' => now()->subDays(400)],
        ['log_name' => 'default', 'description' => 'Umum', 'created_at' => now()->subDays(400)],
    ]);

    expect(app(BersihkanActivityLog::class)->execute(365, 'default'))->toBe(1)
        ->and(Activity::query()->sole()->log_name)->toBe('audit');
});
