@extends('cetak.layouts.surat', ['tampilkanJudul' => false, 'tampilkanTtd' => false])

@section('isi')
    <style>
        table.kepala-surat { margin-bottom: 14px; }
        table.kepala-surat td { padding: 1px 4px 1px 0; vertical-align: top; }
        .tujuan-surat { margin-bottom: 14px; }
        .isi-surat p { margin: 0 0 8px; text-align: justify; }
        .isi-surat table { width: auto; margin: 4px 0 10px 24px; }
        .isi-surat table td { padding: 1px 8px 1px 0; vertical-align: top; }
        .isi-surat table td p { margin: 0; }
        .isi-surat ol, .isi-surat ul { margin: 0 0 8px; padding-left: 22px; }
        table.ttd-surat { margin-top: 18px; page-break-inside: avoid; }
        table.ttd-surat td { width: 50%; text-align: center; vertical-align: top; }
        table.ttd-surat .ruang { height: 64px; }
    </style>

    @include('cetak.partials.badan-surat-keluar', ['surat' => $surat])
@endsection
