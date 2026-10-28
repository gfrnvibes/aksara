@extends('cetak.layouts.surat')

@section('isi')
    <p>Yang bertanda tangan di bawah ini, Kepala {{ config('sekolah.nama') }}, menerangkan bahwa:</p>

    @include('cetak.partials.data-siswa-surat', ['siswa' => $mutasi->siswa, 'kelas' => $mutasi->kelas?->nama ?? $mutasi->siswa->kelas?->nama])

    <p>
        terhitung mulai tanggal {{ $mutasi->tanggal->translatedFormat('d F Y') }} telah pindah sekolah
        @if (filled($mutasi->sekolah))
            ke <strong>{{ $mutasi->sekolah }}</strong>
        @endif
        @if (filled($mutasi->alasan))
            dengan alasan {{ $mutasi->alasan }}
        @endif.
        Selama menjadi siswa {{ config('sekolah.nama') }}, yang bersangkutan tidak memiliki tanggungan administrasi yang belum diselesaikan.
    </p>

    <p>Bersama surat ini kami sertakan salinan buku induk dan rapor siswa yang bersangkutan.</p>

    <p>Demikian surat keterangan pindah ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>
@endsection
