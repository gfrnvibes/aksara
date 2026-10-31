@php
    /** @var \App\Models\SuratKeluar $surat */
    $kepala = $surat->kepalaMadrasah();
    $titik = '...............................................';
@endphp
<table class="kepala-surat">
    <tr><td style="width: 15%;">Nomor</td><td style="width: 12px;">:</td><td>{{ $surat->nomor }}</td></tr>
    @if (($surat->gunakan_sifat ?? false) === true)
        <tr><td>Sifat</td><td>:</td><td>{{ $surat->sifat?->getLabel() ?? '-' }}</td></tr>
    @endif
    <tr><td>Lampiran</td><td>:</td><td>{{ $surat->teksSiapCetak($surat->lampiran) ?: '-' }}</td></tr>
    <tr><td>Perihal</td><td>:</td><td><strong>{{ $surat->teksSiapCetak($surat->perihal) ?: '...' }}</strong></td></tr>
</table>

<div class="tujuan-surat">
    Kepada Yth.<br>
    <strong>{{ $surat->teksSiapCetak($surat->tujuan) ?: '...' }}</strong><br>
    di Tempat
</div>

<div class="isi-surat">
    {!! $surat->isiSiapCetak() !!}
</div>

{{-- When there is only one signer, it is placed on the right with the date and place intact. --}}
<table class="ttd-surat">
    <tr>
        @if (filled($surat->nama_penanda_tangan) && (($surat->tanda_tangan_kepala_madrasah ?? true) === true))
            <td>
                <br>
                {{ $surat->jabatan_penanda_tangan }}
                <div class="ruang"></div>
                <strong><u>{{ $surat->nama_penanda_tangan }}</u></strong><br>
                @if (filled($surat->nip_penanda_tangan))
                    NIP. {{ $surat->nip_penanda_tangan }}
                @endif
            </td>
            <td>
                {{ config('sekolah.tempat_tanda_tangan') }}, {{ $surat->tanggal?->translatedFormat('d F Y') }}<br>
                Kepala Madrasah
                <div class="ruang"></div>
                <strong><u>{{ $kepala['nama'] ?: $titik }}</u></strong><br>
                @if (filled($kepala['nip']))
                    NIP. {{ $kepala['nip'] }}
                @endif
            </td>
        @elseif (filled($surat->nama_penanda_tangan))
            <td></td>
            <td>
                {{ config('sekolah.tempat_tanda_tangan') }}, {{ $surat->tanggal?->translatedFormat('d F Y') }}<br>
                {{ $surat->jabatan_penanda_tangan ?: 'Penanda Tangan' }}
                <div class="ruang"></div>
                <strong><u>{{ $surat->nama_penanda_tangan }}</u></strong><br>
                @if (filled($surat->nip_penanda_tangan))
                    NIP. {{ $surat->nip_penanda_tangan }}
                @endif
            </td>
        @elseif (($surat->tanda_tangan_kepala_madrasah ?? true) === true)
            <td></td>
            <td>
                {{ config('sekolah.tempat_tanda_tangan') }}, {{ $surat->tanggal?->translatedFormat('d F Y') }}<br>
                Kepala Madrasah
                <div class="ruang"></div>
                <strong><u>{{ $kepala['nama'] ?: $titik }}</u></strong><br>
                @if (filled($kepala['nip']))
                    NIP. {{ $kepala['nip'] }}
                @endif
            </td>
        @endif
    </tr>
</table>
