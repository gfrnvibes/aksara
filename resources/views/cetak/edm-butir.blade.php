<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Otentik EDM {{ $indikator->kode }} — {{ $sekolah['nama'] ?? 'Madrasah' }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #111827;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            line-height: 1.5;
        }
        .sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .toolbar {
            max-width: 900px;
            margin: 0 auto 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            cursor: pointer;
        }
        .btn-primary {
            background: #065f46;
            border-color: #065f46;
            color: #ffffff;
        }
        .kop {
            border-bottom: 2px solid #065f46;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .kop h1 { font-size: 18px; margin: 0; color: #064e3b; }
        .kop p { font-size: 13px; margin: 4px 0 0; color: #4b5563; }
        .meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .box h2 { font-size: 14px; margin: 0 0 8px; color: #065f46; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }
        th { background: #ecfdf5; color: #064e3b; }
        .gallery {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .gallery-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            break-inside: avoid;
        }
        .gallery-item img {
            width: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: 6px;
        }
        .gallery-item p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #374151;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span style="font-size:13px;color:#4b5563;">Laporan Bukti Otentik EDM 2026 — Butir {{ $indikator->kode }}</span>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('akreditasi.edm.unduh-zip', $indikator) }}" class="btn">Unduh Bundel (.ZIP)</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>
    </div>

    <div class="sheet">
        <div class="kop">
            <h1>LAPORAN BUKTI OTENTIK EVALUASI DIRI MADRASAH (EDM 2026)</h1>
            <p>{{ $sekolah['nama'] ?? 'Madrasah Tsanawiyah' }} · Tahun Ajaran {{ $indikator->evaluasi->tahunAjaran->nama ?? '-' }}</p>
        </div>

        <div class="meta">
            <div><strong>Kode Butir:</strong> {{ $indikator->kode }} (Aspek {{ $indikator->aspek }})</div>
            <div><strong>Skor Pilihan:</strong> Tingkat {{ $indikator->skor_final ?? $indikator->skor_saran ?? '-' }}</div>
            <div><strong>Sinkronisasi:</strong> {{ $indikator->terakhir_sinkron_at?->format('d/m/Y H:i') ?? '-' }}</div>
        </div>

        <div class="box">
            <h2>Pertanyaan Indikator</h2>
            <p style="margin:0;font-size:14px;">{{ $indikator->pertanyaan }}</p>
            @if ($skorAktif = ($indikator->skor_final ?? $indikator->skor_saran))
                <p style="margin:8px 0 0;font-size:13px;color:#065f46;">
                    <strong>Keterangan Tingkat {{ $skorAktif }}:</strong>
                    {{ $definisi['tingkat'][$skorAktif] ?? '-' }}
                </p>
            @endif
        </div>

        <div class="box">
            <h2>Ringkasan Data Sistem &amp; Kondisi Nyata</h2>
            <p style="margin:0 0 8px;font-size:13px;color:#374151;">
                <strong>Data Aksara:</strong> {{ $indikator->metrik_sistem['ringkasan'] ?? '-' }}
            </p>
            <p style="margin:0 0 8px;font-size:13px;">
                <strong>Kondisi Nyata:</strong> {{ $indikator->kondisi_nyata ?: 'Belum diisi.' }}
            </p>
            <p style="margin:0;font-size:13px;">
                <strong>Rencana Tindak Lanjut:</strong> {{ $indikator->tindak_lanjut ?: 'Belum diisi.' }}
            </p>
        </div>

        @if (!empty($indikator->rekap_tabel['baris']))
            <div class="box">
                <h2>Rekapitulasi Data Nyata dari Sistem</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            @foreach ($indikator->rekap_tabel['kolom'] ?? [] as $kolom)
                                <th>{{ $kolom }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($indikator->rekap_tabel['baris'] as $idx => $baris)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                @foreach ($baris as $sel)
                                    <td>{{ $sel }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="box">
            <h2>Lampiran Foto &amp; Dokumen Bukti ({{ $daftarBukti->count() }} berkas)</h2>
            @if ($daftarBukti->isEmpty())
                <p style="margin:0;font-size:13px;color:#6b7280;">Belum ada lampiran foto/dokumen pada butir ini.</p>
            @else
                <div class="gallery">
                    @foreach ($daftarBukti as $idx => $item)
                        <div class="gallery-item">
                            @if ($item['is_gambar'])
                                <img src="{{ route('akreditasi.edm.gambar', ['indikator' => $indikator, 'indeks' => $idx]) }}" alt="{{ $item['judul'] }}">
                            @else
                                <div style="padding:20px;text-align:center;background:#f3f4f6;border-radius:6px;font-size:12px;">
                                    Dokumen: {{ basename($item['path']) }}
                                </div>
                            @endif
                            <p><strong>[{{ strtoupper($item['sumber']) }}]</strong> {{ $item['judul'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</body>
</html>
