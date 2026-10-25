@php
    $whatsapp = filled($sekolah['whatsapp'])
        ? 'https://wa.me/'.preg_replace('/\D/', '', $sekolah['whatsapp'])
        : null;
    $tautanKontak = $whatsapp ?? '#kontak';
    $tautanPeta = $sekolah['peta'] ?: 'https://www.google.com/maps/search/'.rawurlencode($sekolah['nama'].' '.$sekolah['wilayah']);
    $sosial = array_filter($sekolah['sosial']);
    $warnaLabel = [
        'blue' => 'border-[#2f5bd3]/40 text-[#2f5bd3] bg-[#2f5bd3]/5',
        'green' => 'border-[#15803d]/40 text-[#15803d] bg-[#15803d]/5',
        'orange' => 'border-[#c2410c]/40 text-[#c2410c] bg-[#c2410c]/5',
    ];
    $bagian = ['profil' => 'Profil', 'program' => 'Program', 'nilai' => 'Nilai', 'kontak' => 'Kontak'];
    $tanyaProgram = fn (string $program): string => $whatsapp
        ? $whatsapp.'?text='.rawurlencode("Assalamu'alaikum, saya ingin bertanya tentang program {$program} di {$sekolah['nama']}.")
        : '#kontak';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sekolah['nama'] }} · {{ $sekolah['jenjang'] }} di {{ $sekolah['wilayah'] }}</title>
    <meta name="description" content="{{ $sekolah['nama'] }}, {{ $sekolah['jenjang'] }} di {{ $sekolah['wilayah'] }}. Pendidikan agama dan umum yang seimbang untuk kelas VII hingga IX.">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        .motif {
            background-image:
                radial-gradient(rgb(255 255 255 / 0.22) 1px, transparent 1.2px),
                linear-gradient(135deg, rgb(255 255 255 / 0.16), transparent 55%);
            background-size: 14px 14px, 100% 100%;
        }
        .ubin {
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.35), inset 0 -2px 0 rgb(0 0 0 / 0.18), 0 6px 14px -6px rgb(0 0 0 / 0.35);
        }
    </style>
</head>
<body class="bg-[#f1f1ef] font-sans text-neutral-900 antialiased selection:bg-emerald-200">
    {{-- Bintang segi delapan, dipakai sebagai logo dan motif --}}
    <svg class="hidden" aria-hidden="true">
        <symbol id="bintang" viewBox="0 0 100 100">
            <rect x="20" y="20" width="60" height="60" />
            <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)" />
        </symbol>
    </svg>

    <header class="sticky top-0 z-40 border-b border-neutral-300/80 bg-[#f1f1ef]/85 backdrop-blur">
        <div class="mx-auto flex h-12 max-w-[1440px] items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-6">
                <a href="#profil" class="flex items-center gap-2" aria-label="{{ $sekolah['nama'] }}">
                    <svg class="size-6 fill-neutral-900"><use href="#bintang" /></svg>
                    <span class="font-mono text-[11px] font-medium tracking-[0.18em] uppercase">{{ $sekolah['nama_singkat'] }}</span>
                </a>
                <nav class="hidden items-center gap-5 md:flex" aria-label="Navigasi utama">
                    @foreach ($bagian as $id => $label)
                        <a href="#{{ $id }}" class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase transition hover:text-neutral-900">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <a href="{{ url('/admin') }}" class="bg-neutral-950 px-3 py-2 font-mono text-[10px] font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">
                Buku Induk Digital
            </a>
        </div>
    </header>

    <nav class="fixed top-1/2 right-0 z-30 hidden -translate-y-1/2 flex-col gap-6 pr-3 min-[1540px]:flex" aria-label="Bagian halaman">
        @foreach ($bagian as $id => $label)
            <a href="#{{ $id }}" class="font-mono text-[10px] tracking-[0.25em] text-neutral-400 uppercase transition [writing-mode:vertical-rl] hover:text-neutral-900">{{ $label }}</a>
        @endforeach
    </nav>

    <main class="mx-auto max-w-[1440px] border-x border-neutral-300/70">
        {{-- 01 Profil --}}
        <section id="profil" class="scroll-mt-12 border-b border-neutral-300/70">
            <div class="flex items-center justify-between border-b border-neutral-300/70 px-4 py-3 sm:px-6">
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">01 / {{ $sekolah['jenjang'] }}</p>
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">{{ $sekolah['wilayah'] }}</p>
            </div>

            <div class="grid gap-12 px-4 py-12 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:py-20">
                <div class="max-w-2xl">
                    <h1 class="text-5xl leading-[0.95] font-semibold tracking-tight sm:text-6xl lg:text-7xl">
                        @if ($mataPelajaran->isNotEmpty())
                            {{ $jumlahMataPelajaran }} mata pelajaran.<br>
                        @else
                            Berilmu dan berakhlak.<br>
                        @endif
                        Satu <span class="text-emerald-700">{{ $sekolah['nama_singkat'] }}.</span>
                    </h1>

                    <p class="mt-8 max-w-xl text-base leading-relaxed text-neutral-600">
                        {{ $sekolah['nama'] }} adalah {{ Str::lower($sekolah['jenjang']) }} di {{ $sekolah['wilayah'] }}.
                        Mata pelajaran keagamaan seperti Al-Qur'an Hadis, Akidah Akhlak, Fikih, Sejarah Kebudayaan Islam,
                        dan Bahasa Arab berjalan berdampingan dengan mata pelajaran umum, agar siswa tumbuh berilmu dan berakhlak.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-2">
                        <a href="{{ $tautanKontak }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="inline-flex items-center gap-2 bg-neutral-950 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">
                            <x-heroicon-o-chat-bubble-left-right class="size-4" />
                            Hubungi madrasah
                        </a>
                        <a href="#program" class="inline-flex items-center gap-2 border border-neutral-300 bg-white/60 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] uppercase transition hover:border-neutral-900">
                            <x-heroicon-o-academic-cap class="size-4" />
                            Lihat program
                        </a>
                        <a href="{{ $tautanPeta }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 border border-neutral-300 bg-white/60 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] uppercase transition hover:border-neutral-900">
                            <x-heroicon-o-map-pin class="size-4" />
                            Lokasi
                        </a>
                    </div>

                    @if (filled($sekolah['npsn']) || filled($sekolah['akreditasi']))
                        <p class="mt-4 font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">
                            @if (filled($sekolah['npsn'])) NPSN {{ $sekolah['npsn'] }} @endif
                            @if (filled($sekolah['npsn']) && filled($sekolah['akreditasi'])) · @endif
                            @if (filled($sekolah['akreditasi'])) Akreditasi {{ $sekolah['akreditasi'] }} @endif
                        </p>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center gap-1 font-mono text-[10px] tracking-[0.18em] uppercase">
                        <span class="border border-neutral-300 px-2 py-1.5 text-neutral-500">Bagikan</span>
                        <button type="button" data-salin="{{ url('/') }}" class="cursor-pointer border border-neutral-300 px-2 py-1.5 uppercase transition hover:border-neutral-900">Salin tautan</button>
                        <a href="https://wa.me/?text={{ rawurlencode($sekolah['nama'].' — '.url('/')) }}" target="_blank" rel="noopener" class="border border-neutral-300 px-2 py-1.5 transition hover:border-neutral-900">WhatsApp</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(url('/')) }}" target="_blank" rel="noopener" class="border border-neutral-300 px-2 py-1.5 transition hover:border-neutral-900">Facebook</a>
                    </div>
                </div>

                @if ($mataPelajaran->isNotEmpty())
                    <div>
                        <div class="text-right">
                            <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">{{ $mataPelajaran->count() }} mata pelajaran</p>
                            <p class="mt-1 text-2xl font-semibold tracking-tight uppercase sm:text-3xl">Kurikulum</p>
                        </div>
                        <ul class="mt-8 grid grid-cols-4 gap-x-3 gap-y-6 sm:grid-cols-5">
                            @foreach ($mataPelajaran as $mapel)
                                <li class="flex flex-col items-center gap-2 text-center" title="{{ $mapel['nama'] }}">
                                    <span class="ubin motif grid size-14 place-items-center rounded-2xl sm:size-16" style="background-color: {{ $mapel['warna'] }}">
                                        <x-dynamic-component :component="$mapel['ikon']" class="size-7 text-white" />
                                    </span>
                                    <span class="text-[10px] leading-tight text-neutral-600 sm:text-[11px]">{{ $mapel['singkatan'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        {{-- 02 Program --}}
        <section id="program" class="scroll-mt-12 border-b border-neutral-300/70">
            <div class="flex items-end justify-between border-b border-neutral-300/70 px-4 py-3 sm:px-6">
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">02 / Program pembelajaran</p>
                <h2 class="text-2xl font-semibold tracking-tight uppercase sm:text-3xl">Program</h2>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($program as $item)
                    <article class="flex flex-col border-b border-neutral-300/70 bg-[#f7f7f5] sm:border-r">
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="px-1.5 py-0.5 font-mono text-[10px] text-white" style="background-color: {{ $item['warna'] }}">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">{{ $item['kategori'] }}</span>
                        </div>
                        <div class="motif relative h-36 overflow-hidden" style="background-color: {{ $item['warna'] }}">
                            <svg class="absolute -right-8 -bottom-10 size-44 fill-white/10" aria-hidden="true"><use href="#bintang" /></svg>
                            <x-dynamic-component :component="$item['ikon']" class="absolute top-5 left-5 size-10 text-white/35" />
                        </div>
                        <div class="relative flex flex-1 flex-col px-5 pb-5">
                            <span class="ubin -mt-7 grid size-14 place-items-center rounded-2xl" style="background-color: {{ $item['warna'] }}">
                                <x-dynamic-component :component="$item['ikon']" class="size-7 text-white" />
                            </span>
                            <h3 class="mt-4 text-2xl font-semibold tracking-tight">
                                {{ $item['judul'] }} <span style="color: {{ $item['warna'] }}">{{ $item['aksen'] }}</span>
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $item['deskripsi'] }}</p>

                            @if ($loop->last && $ekstrakurikuler->isNotEmpty())
                                <p class="mt-3 text-sm text-neutral-600">{{ $ekstrakurikuler->join(', ', ' dan ') }}.</p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-1">
                                @foreach ($item['label'] as $label)
                                    <span class="border px-1.5 py-0.5 font-mono text-[9px] tracking-[0.15em] uppercase {{ $warnaLabel[$label['warna']] }}">{{ $label['teks'] }}</span>
                                @endforeach
                            </div>

                            <a href="{{ $tanyaProgram($item['judul'].' '.$item['aksen']) }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="mt-auto inline-flex items-center gap-1 pt-6 font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase transition hover:text-neutral-900">
                                Tanyakan program ini
                                <x-heroicon-o-arrow-up-right class="size-3" />
                            </a>
                        </div>
                    </article>
                @endforeach

                <article class="flex flex-col justify-between border-b border-neutral-300/70 bg-gradient-to-br from-[#f7f7f5] to-[#e7e7e3] p-5 sm:border-r">
                    <div>
                        <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">Selanjutnya</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight">Ingin tahu <span class="text-emerald-700">lebih jauh?</span></h3>
                        <p class="mt-2 text-sm leading-relaxed text-neutral-600">Jadwal pelajaran, kegiatan, dan informasi pendaftaran dapat ditanyakan langsung kepada pihak madrasah.</p>
                    </div>
                    <a href="#kontak" class="mt-8 inline-flex w-fit items-center gap-2 bg-neutral-950 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">
                        Kontak madrasah
                    </a>
                </article>
            </div>
        </section>

        {{-- 03 Nilai --}}
        <section id="nilai" class="scroll-mt-12 border-b border-neutral-300/70">
            <div class="flex items-end justify-between border-b border-neutral-300/70 px-4 py-3 sm:px-6">
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">03 / Yang kami pegang</p>
                <h2 class="text-2xl font-semibold tracking-tight uppercase sm:text-3xl">Nilai</h2>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($nilai as $item)
                    <article class="border-b border-neutral-300/70 bg-[#f7f7f5] p-6 sm:border-r">
                        <div class="flex items-center justify-between">
                            <span class="grid size-8 place-items-center border border-neutral-300">
                                <x-dynamic-component :component="$item['ikon']" class="size-4 text-emerald-700" />
                            </span>
                            <span class="font-mono text-[10px] text-neutral-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <h3 class="mt-8 text-lg font-semibold tracking-tight">{{ $item['judul'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $item['deskripsi'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- 04 Kontak --}}
        <section id="kontak" class="scroll-mt-12 border-b border-neutral-300/70">
            <div class="flex items-end justify-between border-b border-neutral-300/70 px-4 py-3 sm:px-6">
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">04 / Penerimaan peserta didik baru</p>
                <h2 class="text-2xl font-semibold tracking-tight uppercase sm:text-3xl">Bergabung</h2>
            </div>

            <div class="px-4 py-20 text-center sm:px-6">
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">Kelas VII · {{ $sekolah['nama'] }}</p>
                <p class="mx-auto mt-4 max-w-xl text-lg leading-relaxed text-neutral-700">
                    Informasi jadwal, persyaratan, dan tata cara pendaftaran peserta didik baru dapat ditanyakan langsung kepada madrasah.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-2">
                    <a href="{{ $whatsapp ? $whatsapp.'?text='.rawurlencode("Assalamu'alaikum, saya ingin bertanya tentang pendaftaran di {$sekolah['nama']}.") : $tautanPeta }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-neutral-950 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">
                        {{ $whatsapp ? 'Tanya lewat WhatsApp' : 'Kunjungi madrasah' }}
                    </a>
                    <a href="{{ $tautanPeta }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 border border-neutral-300 bg-white/60 px-4 py-3 font-mono text-[10px] font-medium tracking-[0.18em] uppercase transition hover:border-neutral-900">
                        Buka peta
                        <x-heroicon-o-arrow-up-right class="size-3" />
                    </a>
                </div>
            </div>

            @php
                $kontak = array_filter([
                    ['ikon' => 'heroicon-o-map-pin', 'label' => 'Alamat', 'isi' => $sekolah['alamat'], 'url' => $tautanPeta],
                    ['ikon' => 'heroicon-o-phone', 'label' => 'Telepon', 'isi' => $sekolah['telepon'], 'url' => filled($sekolah['telepon']) ? 'tel:'.preg_replace('/[^\d+]/', '', $sekolah['telepon']) : null],
                    ['ikon' => 'heroicon-o-envelope', 'label' => 'Email', 'isi' => $sekolah['email'], 'url' => filled($sekolah['email']) ? 'mailto:'.$sekolah['email'] : null],
                ], fn (array $baris): bool => filled($baris['isi']));
            @endphp
            @if ($kontak !== [])
                <div class="grid border-t border-neutral-300/70 sm:grid-cols-3">
                    @foreach ($kontak as $baris)
                        <a href="{{ $baris['url'] }}" class="flex gap-4 border-b border-neutral-300/70 bg-[#f7f7f5] p-6 transition hover:bg-white sm:border-r sm:border-b-0">
                            <x-dynamic-component :component="$baris['ikon']" class="size-5 shrink-0 text-emerald-700" />
                            <span>
                                <span class="block font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">{{ $baris['label'] }}</span>
                                <span class="mt-1 block text-sm text-neutral-700">{{ $baris['isi'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <footer class="grid gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.5fr_repeat(3,1fr)]">
            <div>
                <svg class="size-8 fill-neutral-900"><use href="#bintang" /></svg>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-neutral-600">{{ $sekolah['slogan'] ?: $sekolah['nama'].', '.Str::lower($sekolah['jenjang']).' di '.$sekolah['wilayah'].'.' }}</p>
                @if (filled($sekolah['email']))
                    <a href="mailto:{{ $sekolah['email'] }}" class="mt-4 block font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase hover:text-neutral-900">{{ $sekolah['email'] }}</a>
                @endif
            </div>
            <div>
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">Madrasah</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($bagian as $id => $label)
                        <li><a href="#{{ $id }}" class="text-neutral-700 hover:text-neutral-950">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">Layanan</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ url('/admin') }}" class="text-neutral-700 hover:text-neutral-950">Buku Induk Digital</a></li>
                    <li><a href="{{ $tautanPeta }}" target="_blank" rel="noopener" class="text-neutral-700 hover:text-neutral-950">Lokasi madrasah</a></li>
                    @if ($whatsapp)
                        <li><a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="text-neutral-700 hover:text-neutral-950">WhatsApp</a></li>
                    @endif
                </ul>
            </div>
            @if ($sosial !== [])
                <div>
                    <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase">Media sosial</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($sosial as $nama => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-neutral-700 hover:text-neutral-950">{{ $nama }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </footer>

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-300/70 px-4 py-4 font-mono text-[10px] tracking-[0.18em] text-neutral-400 uppercase sm:px-6">
            <span>© {{ now()->year }} {{ $sekolah['nama'] }}</span>
            <span>Berilmu · Berakhlak</span>
        </div>
    </main>

    <script>
        document.querySelectorAll('[data-salin]').forEach((tombol) => {
            tombol.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(tombol.dataset.salin);
                    const teks = tombol.textContent;
                    tombol.textContent = 'Tersalin';
                    setTimeout(() => (tombol.textContent = teks), 1500);
                } catch {
                    window.prompt('Salin tautan ini:', tombol.dataset.salin);
                }
            });
        });
    </script>
</body>
</html>
