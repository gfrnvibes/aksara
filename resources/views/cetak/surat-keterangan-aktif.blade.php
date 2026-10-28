@extends('cetak.layouts.surat')

@section('isi')
    <p>Yang bertanda tangan di bawah ini, Kepala {{ config('sekolah.nama') }}, menerangkan bahwa:</p>

    @include('cetak.partials.data-siswa-surat', ['siswa' => $siswa, 'kelas' => $siswa->kelas?->nama])

    <p>
        adalah benar siswa {{ config('sekolah.nama') }} yang masih aktif mengikuti kegiatan belajar
        @if ($tahunAjaran)
            pada Tahun Pelajaran {{ $tahunAjaran->nama }} {{ $semester->getLabel() }}
        @endif.
    </p>

    @if (filled($keperluan))
        <p>Surat keterangan ini dibuat untuk keperluan {{ $keperluan }}.</p>
    @endif

    <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>
@endsection
