@php
    /** @var \App\Models\SuratKeluar $surat */
    $kepala = $surat->kepalaMadrasah();
    $titik = '...............................................';
@endphp
<table class="kepala-surat">
    <tr><td style="width: 15%;">Nomor</td><td style="width: 12px;">:</td><td>{{ $surat->nomor }}</td></tr>
    <tr><td>Sifat</td><td>:</td><td>{{ $surat->sifat?->getLabel() ?? '-' }}</td></tr>
    <tr><td>Lampiran</td><td>:</td><td>{{ $surat->lampiran ?: '-' }}</td></tr>
    <tr><td>Perihal</td><td>:</td><td><strong>{{ $surat->perihal ?: '...' }}</strong></td></tr>
</table>

<div class="tujuan-surat">
    Kepada Yth.<br>
    <strong>{{ $surat->tujuan ?: '...' }}</strong><br>
    di Tempat
</div>

<div class="isi-surat">
    {!! $surat->isiSiapCetak() !!}
</div>

{{-- The principal always signs on the right, under the place and date; an additional signer sits on the left. --}}
<table class="ttd-surat">
    <tr>
        <td>
            @if (filled($surat->nama_penanda_tangan))
                <br>
                {{ $surat->jabatan_penanda_tangan }}
                <div class="ruang"></div>
                <strong><u>{{ $surat->nama_penanda_tangan }}</u></strong><br>
                @if (filled($surat->nip_penanda_tangan))
                    NIP. {{ $surat->nip_penanda_tangan }}
                @endif
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
    </tr>
</table>
