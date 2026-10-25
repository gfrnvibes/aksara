<?php

use App\Models\NilaiRapor;

it('converts a score into a letter grade relative to the KKM', function (?int $nilai, int $kkm, ?string $predikat) {
    expect(NilaiRapor::hitungPredikat($nilai, $kkm))->toBe($predikat);
})->with([
    'no score yet' => [null, 70, null],
    'below KKM' => [69, 70, 'D'],
    'exactly KKM' => [70, 70, 'C'],
    'just below B' => [79, 70, 'C'],
    'lowest B' => [80, 70, 'B'],
    'just below A' => [89, 70, 'B'],
    'lowest A' => [90, 70, 'A'],
    'perfect score' => [100, 70, 'A'],
]);
