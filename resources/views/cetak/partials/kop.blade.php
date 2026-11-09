@php
    $kop = $kop ?? ($surat->kop_surat_snapshot ?? \App\Services\Surat\PengaturanKop::untuk($jenisKop ?? 'surat_keterangan'));
@endphp

@if ($kop['legacy'] ?? false)
    <div class="kop">
        <div class="instansi">{{ $kop['instansi'] }}</div>
        <div class="madrasah">{{ strtoupper($kop['nama']) }}</div>

@if (filled($kop['alamat'] ?? null))
            <div class="alamat">{{ $kop['alamat'] }}</div>

@endif
        <div class="alamat">{{ $kop['wilayah'] ?? '' }}</div>
    </div>

@else
    @php
        $kop = [...\App\Services\Surat\PengaturanKop::bawaan(), ...$kop];
        $kiri = $kop['tampilkan_logo_kiri'] ? \App\Services\Surat\PengaturanKop::gambar($kop['logo_kiri']) : null;
        $kanan = $kop['tampilkan_logo_kanan'] ? \App\Services\Surat\PengaturanKop::gambar($kop['logo_kanan']) : null;
        $gambarKop = \App\Services\Surat\PengaturanKop::gambar($kop['gambar']);
        $perataan = in_array($kop['perataan'], ['left', 'center', 'right'], true) ? $kop['perataan'] : 'center';
        $garis = in_array($kop['garis'], ['none', 'solid', 'double'], true) ? $kop['garis'] : 'double';
    @endphp
    <div class="kop kop-kustom" style="text-align: {{ $perataan }}; border-bottom: {{ $garis === 'double' ? '3px' : '1px' }} {{ $garis }} #000; padding-bottom: 2mm; margin-bottom: {{ (float) $kop['jarak_bawah_mm'] }}mm; line-height: {{ (float) $kop['jarak_baris'] }}; font-size: {{ (float) $kop['ukuran_huruf'] }}pt; page-break-inside: avoid;">

@if ($kop['mode'] === 'gambar')

@if ($gambarKop)
                <img src="{{ $gambarKop }}" alt="Kop surat" style="max-width: 100%; max-height: {{ (float) $kop['tinggi_gambar_mm'] }}mm; width: auto; height: auto; vertical-align: middle;">

@else
                <div>Gambar kop belum tersedia.</div>

@endif

@else
            <table role="presentation" style="width: 100%; border-collapse: collapse; border: 0; margin: 0;"><tr>

@if ($kiri)
                    <td style="width: {{ (float) $kop['logo_kiri_mm'] + 4 }}mm; border: 0; padding: 0 4mm 0 0; vertical-align: middle;">
                        <img src="{{ $kiri }}" alt="Logo kiri" style="max-width: {{ (float) $kop['logo_kiri_mm'] }}mm; max-height: {{ (float) $kop['logo_kiri_mm'] }}mm; width: auto; height: auto;">
                    </td>

@endif
                <td style="border: 0; padding: 0; text-align: {{ $perataan }}; vertical-align: middle;">

@if (filled($kop['instansi']))<div style="font-weight: bold;">{{ $kop['instansi'] }}</div>
@endif
                    <div style="font-weight: bold; font-size: {{ (float) $kop['ukuran_nama'] }}pt;">{{ $kop['nama'] }}</div>

@if (filled($kop['alamat']))<div style="white-space: pre-line;">{{ $kop['alamat'] }}</div>
@endif

@if (filled($kop['wilayah']))<div>{{ $kop['wilayah'] }}</div>
@endif

@if (filled($kop['telepon']) || filled($kop['email']))<div>{{ filled($kop['telepon']) ? 'Telp. '.$kop['telepon'] : '' }}{{ filled($kop['telepon']) && filled($kop['email']) ? ' · ' : '' }}{{ $kop['email'] }}</div>
@endif

@if (filled($kop['website']))<div>{{ $kop['website'] }}</div>
@endif

@if (filled($kop['npsn']) || filled($kop['nsm']))<div>{{ filled($kop['npsn']) ? 'NPSN: '.$kop['npsn'] : '' }}{{ filled($kop['npsn']) && filled($kop['nsm']) ? ' · ' : '' }}{{ filled($kop['nsm']) ? 'NSM: '.$kop['nsm'] : '' }}</div>
@endif
                </td>

@if ($kanan)
                    <td style="width: {{ (float) $kop['logo_kanan_mm'] + 4 }}mm; border: 0; padding: 0 0 0 4mm; vertical-align: middle;">
                        <img src="{{ $kanan }}" alt="Logo kanan" style="max-width: {{ (float) $kop['logo_kanan_mm'] }}mm; max-height: {{ (float) $kop['logo_kanan_mm'] }}mm; width: auto; height: auto;">
                    </td>

@endif
            </tr></table>

@endif
    </div>

@endif
