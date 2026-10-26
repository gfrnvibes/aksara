<?php

/*
|--------------------------------------------------------------------------
| Identitas Madrasah
|--------------------------------------------------------------------------
|
| Data yang tampil di landing page. Isi dengan data resmi madrasah; nilai
| yang dibiarkan null atau kosong tidak ditampilkan di halaman.
|
*/

return [

    'nama' => env('SEKOLAH_NAMA', 'MTs At-Taufiq Cisurupan'),

    'nama_singkat' => env('SEKOLAH_NAMA_SINGKAT', 'At-Taufiq'),

    'jenjang' => 'Madrasah Tsanawiyah',

    'wilayah' => env('SEKOLAH_WILAYAH', 'Cisurupan, Garut, Jawa Barat'),

    'slogan' => env('SEKOLAH_SLOGAN'),

    'npsn' => env('SEKOLAH_NPSN'),

    'akreditasi' => env('SEKOLAH_AKREDITASI'),

    'alamat' => env('SEKOLAH_ALAMAT'),

    'telepon' => env('SEKOLAH_TELEPON'),

    /*
     * Nomor WhatsApp dalam format internasional tanpa tanda +, misalnya 6281234567890.
     */
    'whatsapp' => env('SEKOLAH_WHATSAPP'),

    'email' => env('SEKOLAH_EMAIL'),

    /*
     * Tautan Google Maps. Jika kosong, tombol lokasi mencari nama madrasah di Google Maps.
     */
    'peta' => env('SEKOLAH_PETA'),

    /*
     * Tautan media sosial resmi, label => URL. Baris dengan URL kosong tidak ditampilkan.
     */
    'sosial' => [
        'Instagram' => env('SEKOLAH_INSTAGRAM'),
        'Facebook' => env('SEKOLAH_FACEBOOK'),
        'YouTube' => env('SEKOLAH_YOUTUBE'),
        'TikTok' => env('SEKOLAH_TIKTOK'),
    ],

    /*
     * Days absent without notice in one semester above which the homeroom note asks for better attendance.
     */
    'batas_tanpa_keterangan' => (int) env('SEKOLAH_BATAS_TANPA_KETERANGAN', 2),

    /*
     * Place printed above the principal's signature on the report, e.g. "Cisurupan, 20 Juni 2026".
     */
    'tempat_tanda_tangan' => env('SEKOLAH_TEMPAT_TANDA_TANGAN', 'Cisurupan'),

];
