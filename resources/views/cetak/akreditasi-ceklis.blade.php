@php
    /** @var \App\Models\TahunAjaran $tahun */
    /** @var \Illuminate\Support\Collection<string, \App\Models\AkreditasiDokumen> $dokumens */
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\AkreditasiBukti>> $buktis */
    /** @var array<string, int> $kesiapan */
    /** @var array<string, mixed> $kop */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Ceklis Kesiapan Visitasi - {{ $tahun->nama }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: "Arial", sans-serif; font-size: 9pt; color: #111; }
        h1 { text-align: center; font-size: 12pt; margin: 2mm 0; text-transform: uppercase; }
        .meta { text-align: center; font-size: 9.5pt; margin-bottom: 4mm; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        th, td { border: 1px solid #374151; padding: 1.5mm 2mm; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        .ok { color: #065f46; font-weight: bold; }
        .belum { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    @include('cetak.partials.kop', ['kop' => $kop])

    <h1>Lembar Ceklis Kesiapan Visitasi Akreditasi BAN-PDM</h1>
    <div class="meta">Tahun Ajaran {{ $tahun->nama }} · Kesiapan Keseluruhan: {{ $kesiapan['persen_keseluruhan'] }}%</div>

    <h3>A. 6 Dokumen Wajib Pra-Visitasi Sispena</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Kode</th>
                <th style="width: 67%;">Nama Dokumen Wajib</th>
                <th style="width: 25%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Services\Akreditasi\PetaBanPdm::dokumenWajib() as $dw)
                @php $st = $dokumens->get($dw['kode'])?->status ?? 'belum'; @endphp
                <tr>
                    <td>{{ strtoupper($dw['kode']) }}</td>
                    <td>{{ $dw['judul'] }}</td>
                    <td class="{{ $st === 'belum' ? 'belum' : 'ok' }}">{{ strtoupper($st) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>B. 14 Butir Instrumen Akreditasi (DKA & Bukti Dukung)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 6%;">No</th>
                <th style="width: 44%;">Indikator Butir</th>
                <th style="width: 20%;">Status DKA</th>
                <th style="width: 15%;">Bukti Ter-tag</th>
                <th style="width: 15%;">Cek Fisik</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Services\Akreditasi\PetaBanPdm::butir14() as $b)
                @php
                    $st = $dokumens->get($b['kode'])?->status ?? 'belum';
                    $jml = $buktis->get($b['kode'], collect())->count();
                @endphp
                <tr>
                    <td>{{ $b['nomor'] }}</td>
                    <td><strong>{{ $b['judul'] }}</strong><br><small>{{ $b['bukti_kunci'] }}</small></td>
                    <td class="{{ $st === 'belum' ? 'belum' : 'ok' }}">{{ strtoupper($st) }}</td>
                    <td>{{ $jml }} item</td>
                    <td>[ &nbsp; ] Ada</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
