<?php

use App\Enums\KeputusanKenaikan;
use App\Services\CatatanWaliKelas\CatatanBerbasisAturan;
use App\Services\CatatanWaliKelas\RingkasanRapor;

/**
 * @param  array<string, mixed>  $ubah
 */
function ringkasan(array $ubah = []): RingkasanRapor
{
    return new RingkasanRapor(...[
        'mataPelajaran' => [['nama' => 'Matematika', 'kkm' => 75, 'pengetahuan' => 85, 'keterampilan' => 85, 'sikap' => 'B']],
        'ekstrakurikuler' => [],
        'akhlak' => null,
        'kepribadian' => null,
        'sakit' => 1,
        'izin' => 0,
        'tanpaKeterangan' => 0,
        ...$ubah,
    ]);
}

it('opens with the level of the average score', function (int $nilai, string $pembuka) {
    $catatan = (new CatatanBerbasisAturan)->buat(ringkasan([
        'mataPelajaran' => [['nama' => 'Matematika', 'kkm' => 75, 'pengetahuan' => $nilai, 'keterampilan' => $nilai, 'sikap' => null]],
    ]));

    expect($catatan)->toStartWith($pembuka);
})->with([
    'sangat baik' => [90, 'Ananda meraih hasil belajar yang sangat baik'],
    'baik' => [80, 'Ananda menunjukkan hasil belajar yang baik'],
    'tuntas' => [75, 'Ananda telah mencapai ketuntasan belajar'],
    'belum tuntas' => [70, 'Ananda masih perlu meningkatkan hasil belajar'],
]);

it('says so when no subject has a score yet', function () {
    expect((new CatatanBerbasisAturan)->buat(ringkasan(['mataPelajaran' => []])))
        ->toStartWith('Ananda belum memiliki nilai mata pelajaran');
});

it('describes attendance by the days absent', function (int $sakit, int $izin, int $tanpaKeterangan, string $kalimat) {
    config(['sekolah.batas_tanpa_keterangan' => 2]);

    expect((new CatatanBerbasisAturan)->buat(ringkasan(['sakit' => $sakit, 'izin' => $izin, 'tanpaKeterangan' => $tanpaKeterangan])))
        ->toContain($kalimat);
})->with([
    'di atas batas' => [0, 0, 3, 'Kehadiran perlu ditingkatkan karena terdapat 3 hari tanpa keterangan.'],
    'di bawah batas' => [0, 0, 2, 'Masih terdapat 2 hari tanpa keterangan'],
    'sering sakit' => [4, 2, 0, 'Jaga kesehatan'],
    'selalu hadir' => [0, 0, 0, 'Kehadiran ananda sangat baik.'],
]);

it('points out a large gap between theory and practice', function (int $pengetahuan, int $keterampilan, string $kalimat) {
    expect((new CatatanBerbasisAturan)->buat(ringkasan([
        'mataPelajaran' => [['nama' => 'IPA', 'kkm' => 75, 'pengetahuan' => $pengetahuan, 'keterampilan' => $keterampilan, 'sikap' => null]],
    ])))->toContain($kalimat);
})->with([
    'praktik lebih kuat' => [78, 90, 'Kemampuan praktik ananda lebih menonjol'],
    'teori lebih kuat' => [90, 78, 'Penguasaan teori ananda sudah baik'],
]);

it('closes with the end-of-year decision', function (KeputusanKenaikan $keputusan, ?int $naikKeTingkat, string $penutup) {
    $catatan = (new CatatanBerbasisAturan)->buat(ringkasan(['keputusan' => $keputusan, 'naikKeTingkat' => $naikKeTingkat]));

    expect($catatan)->toEndWith($penutup);
})->with([
    'naik dengan kelas tujuan' => [KeputusanKenaikan::Naik, 8, 'Selamat, ananda dinyatakan naik ke kelas VIII; tetap semangat belajar di kelas yang baru.'],
    'naik tanpa kelas tujuan' => [KeputusanKenaikan::Naik, null, 'Selamat, ananda dinyatakan naik kelas; tetap semangat belajar di kelas yang baru.'],
    'lulus' => [KeputusanKenaikan::Lulus, null, 'Selamat atas kelulusan ananda dari madrasah; semoga sukses di jenjang pendidikan berikutnya.'],
    'tinggal di kelas' => [KeputusanKenaikan::TinggalDiKelas, null, 'Jadikan ini kesempatan untuk memperkuat pemahaman, dengan dukungan madrasah dan orang tua.'],
]);

it('says nothing about promotion when there is no decision', function () {
    expect((new CatatanBerbasisAturan)->buat(ringkasan()))
        ->not->toContain('naik')
        ->not->toContain('lulus');
});

it('asks for guidance on attitude and character graded C or lower', function () {
    $catatan = (new CatatanBerbasisAturan)->buat(ringkasan([
        'mataPelajaran' => [['nama' => 'IPS', 'kkm' => 75, 'pengetahuan' => 80, 'keterampilan' => 80, 'sikap' => 'C']],
        'akhlak' => 'C',
        'kepribadian' => 'B',
    ]));

    expect($catatan)
        ->toContain('Sikap dalam mengikuti pelajaran IPS masih perlu dibina.')
        ->toContain('perlu terus dibina bersama oleh madrasah dan orang tua');
});
