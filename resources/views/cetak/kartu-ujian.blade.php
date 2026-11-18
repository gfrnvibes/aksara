@php
    /** @var \App\Models\KegiatanUjian $kegiatan */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\PenempatanUjian> $peserta */
    /** @var array<string, mixed> $kop */
    $namaMadrasah = strtoupper((string) ($kop['nama'] ?? config('sekolah.nama', 'Madrasah')));
    $semester = $kegiatan->semester === \App\Enums\Semester::Ganjil ? 'Ganjil' : 'Genap';
    $kepala = $kegiatan->penandaTangan;
    $ketua = $kegiatan->ketuaPanitia;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Ujian - {{ $kegiatan->nama }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 8pt;
            color: #111;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #111827;
            color: #fff;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .toolbar button {
            background: #10b981;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .sheet {
            width: 198mm;
            margin: 12px auto;
            background: #fff;
            padding: 2mm;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3mm;
        }
        /* Cut guide border around each card */
        .kartu-wrap {
            border: 1px dashed #6b7280;
            padding: 1.5mm;
            break-inside: avoid;
            page-break-inside: avoid;
            position: relative;
        }
        .kartu {
            border: 1.2px solid #111;
            padding: 2mm 2.5mm;
            height: 66mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .kartu-header {
            text-align: center;
            border-bottom: 1.5px double #111;
            padding-bottom: 1.5mm;
            margin-bottom: 1.5mm;
        }
        .kartu-header .instansi {
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .kartu-header .judul {
            font-size: 8.5pt;
            font-weight: 700;
            margin-top: 0.5mm;
            text-transform: uppercase;
        }
        .kartu-header .sub {
            font-size: 7pt;
            color: #374151;
        }
        .kartu-body {
            display: flex;
            gap: 2.5mm;
            align-items: flex-start;
            flex: 1;
        }
        .foto {
            width: 21mm;
            height: 27mm;
            border: 1px solid #4b5563;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 6.5pt;
            color: #6b7280;
            overflow: hidden;
            background: #f9fafb;
        }
        .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .identitas {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.6pt;
            line-height: 1.25;
        }
        .identitas td {
            padding: 0.4mm 0.5mm;
            vertical-align: top;
        }
        .identitas td.lbl {
            width: 22mm;
            color: #374151;
        }
        .identitas td.sep {
            width: 2.5mm;
        }
        .no-peserta {
            font-weight: 700;
            font-size: 8.5pt;
            letter-spacing: 0.3px;
        }
        .kartu-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 2mm;
            font-size: 6.5pt;
            margin-top: 1mm;
        }
        .catatan {
            font-size: 6.2pt;
            color: #4b5563;
            line-height: 1.15;
            margin-top: 0.8mm;
        }
        .ttd {
            text-align: left;
            min-width: 34mm;
            line-height: 1.15;
        }
        .ttd .nama {
            margin-top: 6mm;
            font-weight: 700;
            text-decoration: underline;
        }
        @media print {
            body {
                background: #fff;
            }
            .toolbar {
                display: none;
            }
            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $kegiatan->nama }}</strong> — {{ $peserta->count() }} Kartu Ujian (Kertas A4, garis putus-putus = garis potong)
        </div>
        <button type="button" onclick="window.print()">Cetak / Print (Ctrl+P)</button>
    </div>

    <div class="sheet">
        @if ($peserta->isEmpty())
            <p style="text-align: center; padding: 24px;">Belum ada peserta ujian yang dipetakan untuk filter ini.</p>
        @else
            <div class="grid">
                @foreach ($peserta as $item)
                    @php
                        $fotoUrl = $item->siswa?->sumberFoto(false);
                    @endphp
                    <div class="kartu-wrap">
                        <div class="kartu">
                            <div class="kartu-header">
                                <div class="instansi">{{ $namaMadrasah }}</div>
                                <div class="judul">Kartu Peserta {{ $kegiatan->nama }}</div>
                                <div class="sub">Tahun Ajaran {{ $kegiatan->tahunAjaran?->nama }} · Semester {{ $semester }}</div>
                            </div>

                            <div class="kartu-body">
                                <div class="foto">
                                    @if ($fotoUrl)
                                        <img src="{{ $fotoUrl }}" alt="Foto {{ $item->siswa?->nama_lengkap }}">
                                    @else
                                        <span>Pas Foto<br>3 × 4</span>
                                    @endif
                                </div>
                                <table class="identitas">
                                    <tr>
                                        <td class="lbl">No. Peserta</td>
                                        <td class="sep">:</td>
                                        <td class="no-peserta">{{ $item->nomor_peserta }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Nama</td>
                                        <td class="sep">:</td>
                                        <td><strong>{{ $item->siswa?->nama_lengkap }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">NIS / NISN</td>
                                        <td class="sep">:</td>
                                        <td>{{ $item->siswa?->nis ?: '-' }} / {{ $item->siswa?->nisn ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Kelas</td>
                                        <td class="sep">:</td>
                                        <td>{{ $item->kelas?->nama ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Ruang / Kursi</td>
                                        <td class="sep">:</td>
                                        <td>
                                            <strong>{{ $item->ruangUjian?->kode ?? '-' }}</strong>
                                            @if ($item->nomor_kursi)
                                                / Kursi {{ str_pad((string) $item->nomor_kursi, 2, '0', STR_PAD_LEFT) }}
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="catatan">
                                {{ $kegiatan->catatan_kartu ?: 'Harap membawa kartu ini selama ujian berlangsung.' }}
                            </div>

                            <div class="kartu-footer">
                                <div class="ttd">
                                    <div>Mengetahui,</div>
                                    <div>{{ $kepala?->jabatan ?? 'Kepala Madrasah' }},</div>
                                    <div class="nama">{{ $kepala?->nama ?? '________________' }}</div>
                                    @if (filled($kepala?->nip))
                                        <div>NIP. {{ $kepala->nip }}</div>
                                    @endif
                                </div>
                                <div class="ttd">
                                    <div>&nbsp;</div>
                                    <div>{{ $ketua?->jabatan ?? 'Ketua Panitia Ujian' }},</div>
                                    <div class="nama">{{ $ketua?->nama ?? '________________' }}</div>
                                    @if (filled($ketua?->nip))
                                        <div>NIP. {{ $ketua->nip }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
