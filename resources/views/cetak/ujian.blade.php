@php
    /** @var \App\Models\Ujian $ujian */
    /** @var string $dokumen naskah | kunci | kisi-kisi */
    /** @var array<string, list<array<string, mixed>>> $naskah */
    use App\Enums\JenisSoal;
    use App\Filament\Resources\Kelas\KelasResource;
    use App\Models\Soal;

    $kelas = KelasResource::getTingkatOptions()[$ujian->tingkat] ?? $ujian->tingkat;
    $judul = ['naskah' => 'Naskah Soal', 'kunci' => 'Kunci Jawaban', 'kisi-kisi' => 'Kisi-kisi Soal'][$dokumen];
    $semester = $ujian->semester === \App\Enums\Semester::Ganjil ? 'Ganjil' : 'Genap';
    $huruf = range('A', 'Z');
    // Wraps each run of Arabic words so it can be set in a larger Arabic face; input must already be escaped.
    $hurufArab = '\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}';
    $arab = fn (string $aman): string => preg_replace("/[{$hurufArab}]+(?:[\s\x{060C}]+[{$hurufArab}]+)*/u", '<span class="arab">$0</span>', $aman) ?? $aman;
    $html = fn (?string $isi): string => $arab((string) str((string) $isi)->sanitizeHtml());
    $teks = fn (?string $isi): string => $arab(e((string) $isi));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} {{ $ujian->nama }} {{ $ujian->mataPelajaran->nama }} Kelas {{ $kelas }}{{ $dokumen === 'naskah' ? ' Paket '.$paket : '' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700" rel="stylesheet">
    <style>
        @page { size: A4; margin: 15mm 15mm 18mm; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; color: #000; margin: 0; }
        /* Larger than the Latin text so the harakat stay legible. */
        .arab { font-family: "Amiri", "Traditional Arabic", serif; font-size: 1.4em; line-height: 1.7; }
        /* A question paragraph in Arabic runs right to left on its own; numbered rows keep their order. */
        .isi p { unicode-bidi: plaintext; margin: 0 0 2pt; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 6pt; text-transform: uppercase; line-height: 1.3; }
        table { border-collapse: collapse; }
        .identitas { width: 100%; margin-bottom: 6pt; }
        .identitas td { padding: 1pt 4pt 1pt 0; vertical-align: top; }
        .paket { border: 1.5px solid #000; font-size: 16pt; font-weight: bold; text-align: center; width: 18mm; }
        .petunjuk { border: 1px solid #000; padding: 4pt 6pt; margin-bottom: 8pt; white-space: pre-line; font-size: 11pt; }
        h2 { font-size: 12pt; margin: 10pt 0 4pt; }
        h2 small { font-weight: normal; font-style: italic; }
        .soal { display: flex; gap: 4pt; margin-bottom: 6pt; break-inside: avoid; }
        .soal > .no { min-width: 7mm; text-align: right; }
        .soal > .badan { flex: 1; }
        .gambar { max-width: 90mm; max-height: 60mm; margin: 2pt 0; display: block; }
        .opsi td { padding: 0 4pt 1pt 0; vertical-align: top; }
        .opsi .gambar { max-width: 50mm; max-height: 35mm; }
        .bs, .jodoh, .daftar { width: 100%; margin-top: 2pt; }
        .bs th, .bs td, .daftar th, .daftar td { border: 1px solid #000; padding: 2pt 4pt; vertical-align: top; }
        .bs th:nth-last-child(-n+2), .bs td:nth-last-child(-n+2) { width: 9mm; text-align: center; }
        .jodoh td { padding: 1pt 4pt; vertical-align: top; width: 50%; }
        .daftar th { background: #eee; }
        .tengah { text-align: center; }
        .pedoman { white-space: pre-line; }
        .halaman-paket { break-before: page; }
        @media print { .tanpa-cetak { display: none; } }
        .tanpa-cetak { position: sticky; top: 0; background: #fffbe6; border-bottom: 1px solid #e5d9a8; padding: 6px 10px; font-family: sans-serif; font-size: 13px; }
        .lembar { max-width: 180mm; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="tanpa-cetak">
        <button type="button" onclick="window.print()">Cetak</button>
        Atur kertas A4 dan nonaktifkan "Headers and footers" di dialog cetak browser.
    </div>

    <div class="lembar">
        @include('cetak.partials.kop', ['jenisKop' => 'naskah_ujian'])

        <h1>{{ $judul }}<br>{{ $ujian->nama }}<br>Tahun Ajaran {{ $ujian->tahunAjaran->nama }} Semester {{ $semester }}</h1>

        <table class="identitas">
            <tr>
                <td style="width: 28mm">Mata Pelajaran</td><td>: {{ $ujian->mataPelajaran->nama }}</td>
                <td style="width: 28mm">Hari/Tanggal</td><td>: {{ $ujian->tanggal?->translatedFormat('l, d F Y') ?? '....................' }}</td>
                @if ($dokumen === 'naskah')
                    <td rowspan="2" class="paket">{{ $paket }}</td>
                @endif
            </tr>
            <tr>
                <td>Kelas</td><td>: {{ $kelas }}</td>
                <td>Waktu</td><td>: {{ $ujian->durasi_menit }} menit</td>
            </tr>
        </table>

        @if ($dokumen === 'naskah')
            @if (filled($ujian->petunjuk))
                <div class="petunjuk"><strong>Petunjuk Umum</strong>
{{ $ujian->petunjuk }}</div>
            @endif

            @foreach ($naskah[$paket] as $i => $bagian)
                <h2>{{ $huruf[$i] }}. {{ $bagian['jenis']->getLabel() }} <small>{{ $bagian['jenis']->petunjukNaskah() }}</small></h2>

                @foreach ($bagian['butir'] as $butir)
                    @php $soal = $butir['soal']; @endphp
                    <div class="soal">
                        <div class="no">{{ $butir['nomor'] }}.</div>
                        <div class="badan">
                            <div class="isi">{!! $html($soal->pertanyaan) !!}</div>
                            @if ($soal->gambar)
                                <img class="gambar" src="{{ Soal::urlGambar($soal->gambar) }}" alt="Gambar soal {{ $butir['nomor'] }}">
                            @endif

                            @switch($soal->jenis)
                                @case(JenisSoal::PilihanGanda)
                                @case(JenisSoal::PgKompleks)
                                    <table class="opsi">
                                        @foreach ($butir['opsi'] as $opsi)
                                            <tr>
                                                <td>{{ $soal->jenis === JenisSoal::PgKompleks ? '☐' : '' }} {{ $opsi['huruf'] }}.</td>
                                                <td>
                                                    {!! $teks($opsi['teks']) !!}
                                                    @if ($opsi['gambar'])
                                                        <img class="gambar" src="{{ Soal::urlGambar($opsi['gambar']) }}" alt="Pilihan {{ $opsi['huruf'] }}">
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                    @break
                                @case(JenisSoal::BenarSalah)
                                    <table class="bs">
                                        <tr><th style="width: 8mm">No</th><th>Pernyataan</th><th>B</th><th>S</th></tr>
                                        @foreach ($butir['opsi'] as $opsi)
                                            <tr><td class="tengah">{{ $opsi['huruf'] }}</td><td>{!! $teks($opsi['teks']) !!}</td><td></td><td></td></tr>
                                        @endforeach
                                    </table>
                                    @break
                                @case(JenisSoal::Menjodohkan)
                                    <table class="jodoh">
                                        @for ($r = 0; $r < max(count($butir['opsi']), count($butir['kanan'])); $r++)
                                            <tr>
                                                <td>@isset($butir['opsi'][$r]){{ $butir['opsi'][$r]['huruf'] }}. {!! $teks($butir['opsi'][$r]['teks']) !!} ( ...... )@endisset</td>
                                                <td>@isset($butir['kanan'][$r]){{ $butir['kanan'][$r]['huruf'] }}. {!! $teks($butir['kanan'][$r]['teks']) !!}@endisset</td>
                                            </tr>
                                        @endfor
                                    </table>
                                    @break
                            @endswitch
                        </div>
                    </div>
                @endforeach
            @endforeach
        @elseif ($dokumen === 'kunci')
            @foreach ($naskah as $kodePaket => $bagianPaket)
                <div @class(['halaman-paket' => ! $loop->first])>
                    <h2>Paket {{ $kodePaket }}</h2>
                    <table class="daftar">
                        <tr><th style="width: 10mm">No</th><th style="width: 32mm">Jenis</th><th>Kunci</th><th style="width: 14mm">Bobot</th></tr>
                        @foreach ($bagianPaket as $bagian)
                            @foreach ($bagian['butir'] as $butir)
                                <tr>
                                    <td class="tengah">{{ $butir['nomor'] }}</td>
                                    <td>{{ $bagian['jenis']->getLabel() }}</td>
                                    <td @class(['pedoman' => $bagian['jenis'] === JenisSoal::Esai])>{!! $teks($butir['kunci']) !!}</td>
                                    <td class="tengah">{{ $butir['bobot'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr><th colspan="3" style="text-align: right">Skor maksimal</th><th>{{ collect($bagianPaket)->flatMap(fn ($b) => $b['butir'])->sum('bobot') }}</th></tr>
                    </table>
                </div>
            @endforeach
        @else
            <table class="daftar">
                <tr><th style="width: 10mm">No</th><th>Materi</th><th>Indikator Soal</th><th style="width: 22mm">Level</th><th style="width: 30mm">Bentuk Soal</th><th style="width: 14mm">Bobot</th></tr>
                @foreach ($naskah[$paket] as $bagian)
                    @foreach ($bagian['butir'] as $butir)
                        @php $soal = $butir['soal']; @endphp
                        <tr>
                            <td class="tengah">{{ $butir['nomor'] }}</td>
                            <td>{{ $soal->materi }}</td>
                            <td>{{ $soal->indikator ?? '-' }}</td>
                            <td class="tengah">{{ $soal->level_kognitif ? $soal->level_kognitif->value.' / '.$soal->level_kognitif->level() : '-' }}</td>
                            <td>{{ $bagian['jenis']->getLabel() }}</td>
                            <td class="tengah">{{ $butir['bobot'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </table>
        @endif
    </div>
</body>
</html>
