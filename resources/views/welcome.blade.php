@php
    $whatsapp = filled($sekolah['whatsapp'])
        ? 'https://wa.me/'.preg_replace('/\D/', '', $sekolah['whatsapp'])
        : null;
    $tautanKontak = $whatsapp ?? '#kontak';
    $tautanPeta = $sekolah['peta'] ?: 'https://www.google.com/maps/search/'.rawurlencode($sekolah['nama'].' '.$sekolah['wilayah']);
    $sosial = array_filter($sekolah['sosial']);
    $warnaLabel = [
        'blue' => 'border-[#2f5bd3]/30 text-[#2f5bd3] bg-[#2f5bd3]/5',
        'green' => 'border-hutan/30 text-hutan bg-hutan/5',
        'orange' => 'border-[#c2410c]/30 text-[#c2410c] bg-[#c2410c]/5',
    ];
    $bagian = ['#kurikulum' => 'Kurikulum', '#program' => 'Program', '#nilai' => 'Nilai', '#spmb' => 'SPMB', '#kontak' => 'Kontak'];
    $tanyaProgram = fn (string $program): string => $whatsapp
        ? $whatsapp.'?text='.rawurlencode("Assalamu'alaikum, saya ingin bertanya tentang program {$program} di {$sekolah['nama']}.")
        : '#kontak';
    $kontak = array_filter([
        ['ikon' => 'heroicon-o-map-pin', 'label' => 'Alamat', 'isi' => $sekolah['alamat'], 'url' => $tautanPeta],
        ['ikon' => 'heroicon-o-phone', 'label' => 'Telepon', 'isi' => $sekolah['telepon'], 'url' => filled($sekolah['telepon']) ? 'tel:'.preg_replace('/[^\d+]/', '', $sekolah['telepon']) : null],
        ['ikon' => 'heroicon-o-envelope', 'label' => 'Email', 'isi' => $sekolah['email'], 'url' => filled($sekolah['email']) ? 'mailto:'.$sekolah['email'] : null],
    ], fn (array $baris): bool => filled($baris['isi']));
@endphp
<x-layouts::publik
    :title="$sekolah['nama'].' · '.$sekolah['jenjang'].' di '.$sekolah['wilayah']"
    :description="$sekolah['nama'].', '.$sekolah['jenjang'].' di '.$sekolah['wilayah'].'. Pendidikan agama dan umum yang seimbang untuk kelas VII hingga IX.'"
    :tautan="$bagian"
>
    <x-slot:aksi>
        <a href="{{ url('/admin') }}" class="hidden rounded-full px-3 py-2 text-sm text-stone-600 transition hover:text-tinta sm:inline-flex">Masuk</a>
        <a href="{{ route('ppdb') }}" class="inline-flex items-center gap-1.5 rounded-full bg-hutan px-4 py-2 text-sm font-medium text-white transition hover:bg-hutan-tua">
            SPMB
            <x-heroicon-m-arrow-right class="size-4" />
        </a>
    </x-slot:aksi>

    {{-- Hero --}}
    <section id="profil" class="relative scroll-mt-16 overflow-hidden border-b border-garis">
        <div class="pola-islami pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <svg class="melayang pointer-events-none absolute top-16 left-[8%] size-10 fill-emas/50" style="--jeda: -1s" aria-hidden="true"><use href="#bintang" /></svg>
        <svg class="melayang pointer-events-none absolute top-40 right-[10%] size-14 fill-hutan/25" style="--jeda: -3s" aria-hidden="true"><use href="#bintang" /></svg>
        <svg class="melayang pointer-events-none absolute bottom-24 left-[16%] hidden size-7 fill-hutan/30 sm:block" style="--jeda: -5s" aria-hidden="true"><use href="#bintang" /></svg>
        <svg class="melayang pointer-events-none absolute right-[22%] bottom-16 hidden size-6 fill-emas/60 sm:block" style="--jeda: -2s" aria-hidden="true"><use href="#bintang" /></svg>

        <div class="relative px-4 pt-16 pb-14 text-center sm:px-6 sm:pt-24 sm:pb-20">
            @if ($spmb)
                <a href="{{ route('ppdb') }}" class="kilau inline-flex items-center gap-2 rounded-full border border-emas/40 bg-emas-muda/70 py-1 pr-3 pl-1 text-sm text-tinta transition hover:border-emas" data-muncul>
                    <span class="rounded-full bg-hutan px-2 py-0.5 text-xs font-medium text-white">Baru</span>
                    SPMB {{ $spmb->nama }} sudah dibuka
                    <x-heroicon-m-arrow-right class="size-3.5" />
                </a>
            @else
                <p class="inline-flex items-center gap-2 rounded-full border border-garis bg-kertas px-3 py-1 font-kode text-[11px] tracking-[0.15em] text-stone-500 uppercase" data-muncul>
                    <svg class="size-3 fill-emas"><use href="#bintang" /></svg>
                    {{ $sekolah['jenjang'] }} · {{ $sekolah['wilayah'] }}
                </p>
            @endif

            <h1 class="mx-auto mt-6 max-w-4xl font-judul text-5xl leading-[1.02] tracking-tight text-tinta sm:text-6xl lg:text-7xl">
                <span class="baris-judul font-semibold"><span>
                    @if ($mataPelajaran->isNotEmpty())
                        {{ $jumlahMataPelajaran }} mata pelajaran.
                    @else
                        Berilmu dan berakhlak.
                    @endif
                </span></span>
                <span class="baris-judul font-light"><span style="--jeda: .15s">Satu <span class="font-medium whitespace-nowrap text-hutan">{{ $sekolah['nama_singkat'] }}.</span></span></span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed" data-muncul style="--jeda: .25s">
                {{ $sekolah['nama'] }} adalah {{ Str::lower($sekolah['jenjang']) }} di {{ $sekolah['wilayah'] }}.
                Al-Qur'an Hadis, Akidah Akhlak, Fikih, Sejarah Kebudayaan Islam, dan Bahasa Arab berjalan berdampingan
                dengan mata pelajaran umum, agar murid tumbuh berilmu dan berakhlak.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3" data-muncul style="--jeda: .35s">
                @if ($spmb)
                    <a href="{{ route('ppdb') }}" class="denyut inline-flex h-12 items-center gap-2 rounded-full bg-hutan pr-4 pl-6 font-medium text-white shadow-lg shadow-hutan/25 transition hover:scale-[1.03] hover:bg-hutan-tua">
                        Daftar SPMB
                        <span class="grid size-7 place-items-center rounded-full bg-white/15"><x-heroicon-m-arrow-right class="size-4" /></span>
                    </a>
                @else
                    <a href="{{ $tautanKontak }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="denyut inline-flex h-12 items-center gap-2 rounded-full bg-hutan pr-4 pl-6 font-medium text-white shadow-lg shadow-hutan/25 transition hover:scale-[1.03] hover:bg-hutan-tua">
                        Hubungi madrasah
                        <span class="grid size-7 place-items-center rounded-full bg-white/15"><x-heroicon-m-chat-bubble-left-right class="size-4" /></span>
                    </a>
                @endif
                <a href="#program" class="inline-flex h-12 items-center gap-2 rounded-full border border-garis bg-kertas px-6 font-medium text-tinta transition hover:border-hutan/40 hover:bg-mint/40">
                    <x-heroicon-o-academic-cap class="size-5 text-hutan" />
                    Lihat program
                </a>
                <a href="{{ $tautanPeta }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-1.5 px-3 text-stone-600 transition hover:text-tinta">
                    <x-heroicon-o-map-pin class="size-5" />
                    Lokasi
                </a>
            </div>

            @if (filled($sekolah['npsn']) || filled($sekolah['akreditasi']))
                <p class="mt-6 font-kode text-[11px] tracking-[0.15em] text-stone-500 uppercase" data-muncul style="--jeda: .45s">
                    @if (filled($sekolah['npsn'])) NPSN {{ $sekolah['npsn'] }} @endif
                    @if (filled($sekolah['npsn']) && filled($sekolah['akreditasi'])) · @endif
                    @if (filled($sekolah['akreditasi'])) Akreditasi {{ $sekolah['akreditasi'] }} @endif
                </p>
            @endif
        </div>

        @php
            $angka = array_filter([
                ['nilai' => $mataPelajaran->count(), 'label' => 'Mata pelajaran'],
                ['nilai' => $ekstrakurikuler->count(), 'label' => 'Ekstrakurikuler'],
                ['nilai' => 3, 'label' => 'Tingkat kelas (VII–IX)'],
                ['nilai' => count($program), 'label' => 'Rumpun program'],
            ], fn (array $item): bool => $item['nilai'] > 0);
        @endphp
        <div class="relative grid grid-cols-2 border-t border-garis bg-kertas/80 backdrop-blur-sm lg:grid-cols-4">
            @foreach ($angka as $item)
                <div class="border-garis px-5 py-6 text-center not-last:border-r max-lg:nth-2:border-r-0 max-lg:nth-[n+3]:border-t" data-muncul style="--jeda: {{ $loop->index * 80 }}ms">
                    <p class="font-judul text-4xl font-semibold text-tinta"><span data-hitung="{{ $item['nilai'] }}">{{ $item['nilai'] }}</span></p>
                    <p class="mt-1 text-sm">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Subjects scrolling past --}}
    @if ($mataPelajaran->isNotEmpty())
        <div class="marquee overflow-hidden border-b border-garis bg-tinta py-3.5 text-white/85" style="--durasi: {{ max(30, $mataPelajaran->count() * 4) }}s" aria-hidden="true">
            <div class="marquee-jalur flex w-max">
                @foreach ([1, 2] as $_)
                    @foreach ($mataPelajaran as $mapel)
                        <span class="flex items-center gap-6 pr-6 font-judul text-lg whitespace-nowrap">
                            {{ $mapel['nama'] }}
                            <svg class="size-3 fill-emas"><use href="#bintang" /></svg>
                        </span>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kurikulum --}}
    @if ($mataPelajaran->isNotEmpty())
        <section id="kurikulum" class="scroll-mt-16 border-b border-garis px-5 py-14 sm:px-10 lg:px-14 lg:py-20">
            <div class="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                <header data-muncul="kiri">
                    <p class="font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">01 · Kurikulum</p>
                    <h2 class="mt-3 font-judul text-4xl font-semibold tracking-tight text-tinta sm:text-5xl">Agama dan umum,</h2>
                    <p class="mt-1 font-judul text-2xl font-light text-tinta sm:text-3xl">berimbang di setiap jenjang.</p>
                    <p class="mt-5 max-w-md leading-relaxed">
                        {{ $mataPelajaran->count() }} mata pelajaran aktif tahun ini, dari rumpun Pendidikan Agama Islam hingga sains, bahasa, dan seni.
                    </p>
                </header>

                <ul class="grid grid-cols-4 gap-x-3 gap-y-6 sm:grid-cols-5">
                    @foreach ($mataPelajaran as $mapel)
                        <li class="group flex flex-col items-center gap-2 text-center" title="{{ $mapel['nama'] }}" data-muncul="skala" style="--jeda: {{ $loop->index * 45 }}ms">
                            <span class="grid size-14 place-items-center rounded-2xl shadow-md ring-1 ring-black/5 transition duration-300 group-hover:-translate-y-1.5 group-hover:rotate-[-6deg] group-hover:shadow-xl sm:size-16" style="background-color: {{ $mapel['warna'] }}; box-shadow: inset 0 1px 0 rgb(255 255 255 / .35), inset 0 -2px 0 rgb(0 0 0 / .15)">
                                <x-dynamic-component :component="$mapel['ikon']" class="size-7 text-white" />
                            </span>
                            <span class="text-[11px] leading-tight text-stone-600 sm:text-xs">{{ $mapel['singkatan'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
        <x-publik.sambungan />
    @endif

    {{-- Program --}}
    <section id="program" class="scroll-mt-16 border-b border-garis">
        <header class="flex flex-wrap items-end justify-between gap-4 px-5 pt-14 pb-10 sm:px-10 lg:px-14 lg:pt-20" data-muncul="kiri">
            <div>
                <p class="font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">02 · Program</p>
                <h2 class="mt-3 font-judul text-4xl font-semibold tracking-tight text-tinta sm:text-5xl">Program pembelajaran</h2>
                <p class="mt-1 font-judul text-2xl font-light text-tinta sm:text-3xl">yang menumbuhkan ilmu dan adab.</p>
            </div>
            <a href="#kontak" class="inline-flex items-center gap-1.5 rounded-full border border-garis px-4 py-2 text-sm text-tinta transition hover:border-hutan/40 hover:bg-mint/40">
                Tanya madrasah
                <x-heroicon-m-arrow-up-right class="size-4" />
            </a>
        </header>

        <div class="grid border-t border-garis sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($program as $item)
                <article class="group relative flex flex-col border-b border-garis p-6 transition duration-300 hover:bg-white sm:border-r" data-muncul style="--jeda: {{ ($loop->index % 4) * 70 }}ms">
                    <div class="flex items-center justify-between">
                        <span class="grid size-12 place-items-center rounded-xl text-white shadow-md transition duration-300 group-hover:scale-110 group-hover:rotate-6" style="background-color: {{ $item['warna'] }}">
                            <x-dynamic-component :component="$item['ikon']" class="size-6" />
                        </span>
                        <span class="font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} · {{ $item['kategori'] }}</span>
                    </div>
                    <h3 class="mt-6 font-judul text-2xl font-semibold tracking-tight text-tinta">
                        {{ $item['judul'] }} <span class="font-light" style="color: {{ $item['warna'] }}">{{ $item['aksen'] }}</span>
                    </h3>
                    <p class="mt-2 text-[15px] leading-relaxed">{{ $item['deskripsi'] }}</p>

                    @if ($loop->last && $ekstrakurikuler->isNotEmpty())
                        <p class="mt-3 text-[15px]">{{ $ekstrakurikuler->join(', ', ' dan ') }}.</p>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($item['label'] as $label)
                            <span class="rounded-full border px-2 py-0.5 text-xs {{ $warnaLabel[$label['warna']] }}">{{ $label['teks'] }}</span>
                        @endforeach
                    </div>

                    <a href="{{ $tanyaProgram($item['judul'].' '.$item['aksen']) }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="mt-auto inline-flex items-center gap-1 pt-6 text-sm text-stone-500 transition group-hover:text-hutan">
                        Tanyakan program ini
                        <x-heroicon-m-arrow-up-right class="size-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                    </a>
                </article>
            @endforeach

            <article class="relative flex flex-col justify-between overflow-hidden border-b border-garis bg-mint/50 p-6 sm:border-r" data-muncul style="--jeda: 210ms">
                <svg class="putar-lambat absolute -right-10 -bottom-12 size-48 fill-hutan/10" aria-hidden="true"><use href="#bintang" /></svg>
                <div class="relative">
                    <p class="font-kode text-[11px] tracking-[0.15em] text-hutan uppercase">Selanjutnya</p>
                    <h3 class="mt-3 font-judul text-2xl font-semibold tracking-tight text-tinta">Ingin tahu <span class="font-light text-hutan">lebih jauh?</span></h3>
                    <p class="mt-2 text-[15px] leading-relaxed">Jadwal pelajaran, kegiatan, dan informasi pendaftaran dapat ditanyakan langsung kepada pihak madrasah.</p>
                </div>
                <a href="#kontak" class="relative mt-8 inline-flex w-fit items-center gap-2 rounded-full bg-tinta px-5 py-2.5 text-sm font-medium text-white transition hover:bg-hutan">
                    Kontak madrasah
                    <x-heroicon-m-arrow-right class="size-4" />
                </a>
            </article>
        </div>
    </section>
    <x-publik.sambungan />

    {{-- Nilai --}}
    <section id="nilai" class="scroll-mt-16 border-b border-garis">
        <header class="px-5 pt-14 pb-10 sm:px-10 lg:px-14 lg:pt-20" data-muncul="kiri">
            <p class="font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">03 · Nilai</p>
            <h2 class="mt-3 font-judul text-4xl font-semibold tracking-tight text-tinta sm:text-5xl">Yang kami pegang</h2>
            <p class="mt-1 font-judul text-2xl font-light text-tinta sm:text-3xl">dalam mendidik setiap murid.</p>
        </header>

        <div class="grid border-t border-garis sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($nilai as $item)
                <article class="group border-b border-garis p-7 transition duration-300 hover:bg-white sm:border-r" data-muncul style="--jeda: {{ ($loop->index % 3) * 80 }}ms">
                    <div class="flex items-center justify-between">
                        <span class="grid size-11 place-items-center rounded-full border border-hutan/25 bg-mint/50 text-hutan transition duration-300 group-hover:bg-hutan group-hover:text-white">
                            <x-dynamic-component :component="$item['ikon']" class="size-5" />
                        </span>
                        <span class="font-kode text-xs text-stone-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <h3 class="mt-7 font-judul text-xl font-semibold tracking-tight text-tinta">{{ $item['judul'] }}</h3>
                    <p class="mt-2 text-[15px] leading-relaxed">{{ $item['deskripsi'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- SPMB --}}
    <section id="spmb" class="relative scroll-mt-16 overflow-hidden border-b border-garis bg-tinta px-5 py-20 text-center text-white/80 sm:px-10 lg:py-28">
        <div class="pola-islami-terang pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <svg class="putar-lambat pointer-events-none absolute -top-20 -left-20 size-72 fill-white/5" aria-hidden="true"><use href="#bintang" /></svg>
        <svg class="melayang pointer-events-none absolute right-[12%] bottom-10 size-10 fill-emas/70" aria-hidden="true"><use href="#bintang" /></svg>

        <div class="relative mx-auto max-w-3xl" data-muncul="skala">
            <p class="font-kode text-[11px] tracking-[0.18em] text-emas-muda uppercase">04 · Sistem Penerimaan Murid Baru</p>
            <h2 class="mt-4 font-judul text-4xl font-semibold tracking-tight text-white sm:text-6xl">
                Bergabung bersama <span class="font-light whitespace-nowrap text-emas-muda">{{ $sekolah['nama_singkat'] }}.</span>
            </h2>
            <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed">
                @if ($spmb)
                    Pendaftaran murid baru kelas VII tahun ajaran {{ $spmb->nama }} sedang dibuka{{ $spmb->ppdb_ditutup ? ' hingga '.$spmb->ppdb_ditutup->translatedFormat('d F Y') : '' }}. Daftar secara daring, unggah berkas, dan pantau statusnya dari rumah.
                @else
                    Informasi jadwal, persyaratan, dan tata cara penerimaan murid baru dapat ditanyakan langsung kepada madrasah.
                @endif
            </p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                @if ($spmb)
                    <a href="{{ route('ppdb') }}" class="denyut inline-flex h-12 items-center gap-2 rounded-full bg-emas-muda pr-4 pl-6 font-medium text-tinta transition hover:scale-[1.03]">
                        Daftar sekarang
                        <span class="grid size-7 place-items-center rounded-full bg-tinta/10"><x-heroicon-m-arrow-right class="size-4" /></span>
                    </a>
                @endif
                <a href="{{ route('ppdb') }}#cek-status" class="inline-flex h-12 items-center gap-2 rounded-full border border-white/20 px-6 text-white transition hover:bg-white/10">
                    Cek status pendaftaran
                </a>
                <a href="{{ $whatsapp ? $whatsapp.'?text='.rawurlencode("Assalamu'alaikum, saya ingin bertanya tentang penerimaan murid baru di {$sekolah['nama']}.") : $tautanPeta }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2 rounded-full px-4 text-white/80 transition hover:text-white">
                    {{ $whatsapp ? 'Tanya lewat WhatsApp' : 'Kunjungi madrasah' }}
                    <x-heroicon-m-arrow-up-right class="size-4" />
                </a>
            </div>
        </div>
    </section>

    {{-- Kontak --}}
    <section id="kontak" class="scroll-mt-16">
        @if ($kontak !== [])
            <div class="grid border-b border-garis sm:grid-cols-3">
                @foreach ($kontak as $baris)
                    <a href="{{ $baris['url'] }}" class="group flex gap-4 border-b border-garis p-7 transition hover:bg-white sm:border-r sm:border-b-0" data-muncul style="--jeda: {{ $loop->index * 80 }}ms">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-mint/60 text-hutan transition group-hover:bg-hutan group-hover:text-white">
                            <x-dynamic-component :component="$baris['ikon']" class="size-5" />
                        </span>
                        <span>
                            <span class="block font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase">{{ $baris['label'] }}</span>
                            <span class="mt-1 block text-tinta">{{ $baris['isi'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        <footer class="grid gap-10 px-5 py-14 sm:px-10 md:grid-cols-[1.5fr_repeat(3,1fr)] lg:px-14">
            <div>
                <svg class="size-9 fill-hutan"><use href="#bintang" /></svg>
                <p class="mt-4 max-w-xs leading-relaxed">{{ $sekolah['slogan'] ?: $sekolah['nama'].', '.Str::lower($sekolah['jenjang']).' di '.$sekolah['wilayah'].'.' }}</p>
                @if (filled($sekolah['email']))
                    <a href="mailto:{{ $sekolah['email'] }}" class="mt-4 block text-sm text-hutan hover:underline">{{ $sekolah['email'] }}</a>
                @endif
                <div class="mt-6 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-stone-400">Bagikan:</span>
                    <button type="button" data-salin="{{ url('/') }}" class="cursor-pointer rounded-full border border-garis px-2.5 py-1 transition hover:border-hutan/40 hover:text-tinta">Salin tautan</button>
                    <a href="https://wa.me/?text={{ rawurlencode($sekolah['nama'].' — '.url('/')) }}" target="_blank" rel="noopener" class="rounded-full border border-garis px-2.5 py-1 transition hover:border-hutan/40 hover:text-tinta">WhatsApp</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(url('/')) }}" target="_blank" rel="noopener" class="rounded-full border border-garis px-2.5 py-1 transition hover:border-hutan/40 hover:text-tinta">Facebook</a>
                </div>
            </div>
            <div>
                <p class="font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase">Madrasah</p>
                <ul class="mt-4 space-y-2">
                    @foreach ($bagian as $url => $label)
                        <li><a href="{{ $url }}" class="transition hover:text-hutan">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase">Layanan</p>
                <ul class="mt-4 space-y-2">
                    <li><a href="{{ route('ppdb') }}" class="transition hover:text-hutan">Pendaftaran SPMB</a></li>
                    <li><a href="{{ url('/admin') }}" class="transition hover:text-hutan">AKSARA (masuk petugas)</a></li>
                    <li><a href="{{ $tautanPeta }}" target="_blank" rel="noopener" class="transition hover:text-hutan">Lokasi madrasah</a></li>
                    @if ($whatsapp)
                        <li><a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="transition hover:text-hutan">WhatsApp</a></li>
                    @endif
                </ul>
            </div>
            @if ($sosial !== [])
                <div>
                    <p class="font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase">Media sosial</p>
                    <ul class="mt-4 space-y-2">
                        @foreach ($sosial as $nama => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener" class="transition hover:text-hutan">{{ $nama }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </footer>
    </section>
</x-layouts::publik>
