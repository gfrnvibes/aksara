@php
    $bagian = [
        route('home').'#kurikulum' => 'Kurikulum',
        route('home').'#program' => 'Program',
        route('home').'#nilai' => 'Nilai',
        route('galeri.index') => 'Galeri',
        route('perpustakaan.index') => 'Perpustakaan',
        route('home').'#spmb' => 'SPMB',
        route('home').'#kontak' => 'Kontak',
    ];
@endphp
<x-layouts::publik
    :title="'Perpustakaan Digital · '.$sekolah['nama']"
    :description="'Koleksi buku paket, PAI, literasi, dan modul digital '.$sekolah['nama'].'.'"
    :tautan="$bagian"
>
    <x-slot:aksi>
        <a href="{{ url('/admin') }}" class="hidden rounded-full px-3 py-2 text-sm text-stone-600 transition hover:text-tinta sm:inline-flex">Masuk</a>
        <a href="{{ route('ppdb') }}" class="inline-flex items-center gap-1.5 rounded-full bg-hutan px-4 py-2 text-sm font-medium text-white transition hover:bg-hutan-tua">
            <span>SPMB</span>
            <x-heroicon-o-arrow-up-right class="size-4" />
        </a>
    </x-slot:aksi>

    <section class="relative overflow-hidden border-b border-garis px-5 py-12 sm:px-10 sm:py-16">
        <div class="pola-islami pointer-events-none absolute inset-0 opacity-60"></div>
        <div class="relative mx-auto max-w-4xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-hutan/25 bg-mint/50 px-3 py-1 font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">
                <x-heroicon-o-book-open class="size-3.5" />
                Perpustakaan Digital
            </span>
            <h1 class="mt-4 font-judul text-3xl leading-tight font-semibold tracking-tight text-tinta sm:text-5xl">
                Koleksi E-Book &amp; Sumber Belajar Madrasah
            </h1>
            <p class="mt-3 max-w-2xl text-base leading-relaxed text-stone-600">
                Akses buku paket pelajaran, kitab &amp; PAI, modul guru, serta bacaan literasi untuk siswa dan orang tua {{ $sekolah['nama'] }}.
            </p>

            <form method="GET" action="{{ route('perpustakaan.index') }}" class="mt-7 grid gap-3 rounded-2xl border border-garis bg-kertas/90 p-3 shadow-sm sm:grid-cols-12">
                <input type="hidden" name="mode" value="{{ $modeAktif }}">
                <div class="sm:col-span-5">
                    <input
                        type="text"
                        name="q"
                        value="{{ $kataKunci }}"
                        placeholder="Cari judul, penulis, penerbit, atau ISBN..."
                        class="w-full rounded-xl border border-garis bg-white px-3.5 py-2.5 text-sm text-tinta placeholder:text-stone-400 focus:border-hutan focus:outline-none"
                    >
                </div>
                <div class="sm:col-span-3">
                    <select name="kategori" class="w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm text-tinta focus:border-hutan focus:outline-none">
                        <option value="">Semua Kategori</option>
                        @foreach ($daftarKategori as $kode => $label)
                            <option value="{{ $kode }}" @selected($kategoriAktif === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <select name="tingkat" class="w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm text-tinta focus:border-hutan focus:outline-none">
                        <option value="">Semua Kelas</option>
                        @foreach ($daftarTingkat as $kode => $label)
                            <option value="{{ $kode }}" @selected($tingkatAktif === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-hutan px-4 py-2.5 text-sm font-medium text-white transition hover:bg-hutan-tua">
                        <x-heroicon-o-magnifying-glass class="size-4" />
                        <span>Cari</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="px-3 py-8 sm:px-10 sm:py-10">
        @if ($paginator->isEmpty())
            <div class="mx-auto max-w-xl rounded-2xl border border-dashed border-garis bg-latar/50 p-10 text-center">
                <x-heroicon-o-book-open class="mx-auto size-10 text-stone-400" />
                <h2 class="mt-3 font-judul text-xl font-semibold text-tinta">Belum ada koleksi buku ditemukan</h2>
                <p class="mt-1 text-sm text-stone-500">Coba ubah kata kunci pencarian atau pilih filter kategori lainnya.</p>
                @if ($kataKunci !== '' || $kategoriAktif !== '' || $tingkatAktif !== null)
                    <a href="{{ route('perpustakaan.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-garis px-4 py-2 text-sm font-medium text-tinta hover:bg-mint/40">
                        Reset Filter
                    </a>
                @endif
            </div>
        @else
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-stone-600 sm:text-sm">
                    Menampilkan <span class="font-semibold text-tinta">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> dari <span class="font-semibold text-tinta">{{ number_format($paginator->total()) }}</span> buku
                </p>

                <div class="inline-flex items-center rounded-xl border border-garis bg-kertas p-1 shadow-xs" role="group" aria-label="Mode tampilan daftar buku">
                    @php
                        $modeOpsi = [
                            'daftar' => ['label' => 'Daftar', 'ikon' => 'list-bullet'],
                            'tabel' => ['label' => 'Tabel', 'ikon' => 'table-cells'],
                            'grid' => ['label' => 'Sampul', 'ikon' => 'squares-2x2'],
                        ];
                    @endphp
                    @foreach ($modeOpsi as $kodeMode => $infoMode)
                        @php $aktif = $modeAktif === $kodeMode; @endphp
                        <a
                            href="{{ request()->fullUrlWithQuery(['mode' => $kodeMode, 'page' => null]) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition sm:px-3 {{ $aktif ? 'bg-hutan text-white shadow-xs' : 'text-stone-600 hover:bg-mint/40 hover:text-tinta' }}"
                            @if ($aktif) aria-current="page" @endif
                        >
                            @if ($infoMode['ikon'] === 'list-bullet')
                                <x-heroicon-o-list-bullet class="size-3.5 sm:size-4" />
                            @elseif ($infoMode['ikon'] === 'table-cells')
                                <x-heroicon-o-table-cells class="size-3.5 sm:size-4" />
                            @else
                                <x-heroicon-o-squares-2x2 class="size-3.5 sm:size-4" />
                            @endif
                            <span>{{ $infoMode['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($modeAktif === 'daftar')
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($paginator as $buku)
                        <article class="flex items-start justify-between gap-3 rounded-2xl border border-garis bg-white/95 p-3.5 shadow-2xs transition hover:border-hutan/40 hover:shadow-sm sm:p-4">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="hidden size-11 shrink-0 items-center justify-center rounded-xl bg-mint/60 text-hutan sm:flex">
                                    <x-heroicon-o-book-open class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full bg-mint/60 px-2 py-0.5 font-kode text-[10px] font-medium text-hutan">
                                            {{ \App\Models\BukuPerpustakaan::KATEGORI[$buku->kategori] ?? $buku->kategori }}
                                        </span>
                                        @if ($buku->tingkat !== null)
                                            <span class="rounded-full bg-hutan px-2 py-0.5 font-kode text-[10px] font-medium text-white">
                                                {{ \App\Models\BukuPerpustakaan::TINGKAT[$buku->tingkat] ?? 'Kelas '.$buku->tingkat }}
                                            </span>
                                        @endif
                                        @if ($buku->mataPelajaran)
                                            <span class="rounded-full border border-amber-300/70 bg-amber-50 px-2 py-0.5 font-kode text-[10px] text-amber-800">
                                                {{ $buku->mataPelajaran->nama }}
                                            </span>
                                        @endif
                                    </div>

                                    <h2 class="mt-1.5 line-clamp-2 font-judul text-sm leading-snug font-semibold text-tinta sm:text-base">
                                        {{ $buku->judul }}
                                    </h2>

                                    <p class="mt-1 line-clamp-1 text-xs text-stone-600">
                                        {{ $buku->penulisRingkas() }}@if ($buku->tahun_terbit) · {{ $buku->tahun_terbit }}@endif
                                    </p>

                                    @if ($buku->penerbit || $buku->sumberLabel() !== '')
                                        <p class="mt-0.5 line-clamp-1 text-[11px] text-stone-500">
                                            {{ collect([$buku->penerbit, $buku->sumberLabel()])->filter()->implode(' · ') }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end justify-between gap-2 self-stretch">
                                <span class="inline-flex items-center gap-1 text-[11px] text-stone-500">
                                    <x-heroicon-o-eye class="size-3.5" />
                                    <span>{{ number_format($buku->jumlah_dibaca) }}</span>
                                </span>
                                <a
                                    href="{{ route('perpustakaan.viewer', $buku) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 rounded-full bg-hutan px-3 py-1.5 text-xs font-medium text-white transition hover:bg-hutan-tua"
                                >
                                    <span>Baca</span>
                                    <x-heroicon-o-book-open class="size-3.5" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @elseif ($modeAktif === 'tabel')
                <div class="overflow-x-auto rounded-2xl border border-garis bg-white/95 shadow-2xs">
                    <table class="min-w-full divide-y divide-garis text-left text-xs sm:text-sm">
                        <thead class="bg-kertas/80 font-kode text-[11px] tracking-wider text-stone-600 uppercase">
                            <tr>
                                <th class="px-4 py-3 font-medium">Judul Buku</th>
                                <th class="px-4 py-3 font-medium">Penulis</th>
                                <th class="hidden px-4 py-3 font-medium md:table-cell">Penerbit &amp; Sumber</th>
                                <th class="hidden px-4 py-3 font-medium sm:table-cell">Kategori &amp; Kelas</th>
                                <th class="px-4 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-garis/70">
                            @foreach ($paginator as $buku)
                                <tr class="transition hover:bg-mint/15">
                                    <td class="px-4 py-3">
                                        <div class="font-judul font-semibold text-tinta">{{ $buku->judul }}</div>
                                        @if ($buku->mataPelajaran)
                                            <div class="mt-0.5 font-kode text-[11px] text-hutan">{{ $buku->mataPelajaran->nama }}</div>
                                        @endif
                                        <div class="mt-1 flex flex-wrap gap-1 sm:hidden">
                                            <span class="rounded-full bg-mint/60 px-2 py-0.5 font-kode text-[10px] text-hutan">
                                                {{ \App\Models\BukuPerpustakaan::KATEGORI[$buku->kategori] ?? $buku->kategori }}
                                            </span>
                                            @if ($buku->tingkat !== null)
                                                <span class="rounded-full bg-hutan px-2 py-0.5 font-kode text-[10px] text-white">
                                                    {{ \App\Models\BukuPerpustakaan::TINGKAT[$buku->tingkat] ?? 'Kelas '.$buku->tingkat }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-stone-700">
                                        <div>{{ $buku->penulisRingkas() }}</div>
                                        @if ($buku->tahun_terbit)
                                            <div class="text-[11px] text-stone-500">{{ $buku->tahun_terbit }}</div>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3 text-stone-600 md:table-cell">
                                        <div>{{ $buku->penerbit ?: '—' }}</div>
                                        @if ($buku->sumberLabel() !== '')
                                            <div class="text-[11px] text-stone-500">{{ $buku->sumberLabel() }}</div>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="rounded-full bg-mint/60 px-2 py-0.5 font-kode text-[10px] font-medium text-hutan">
                                                {{ \App\Models\BukuPerpustakaan::KATEGORI[$buku->kategori] ?? $buku->kategori }}
                                            </span>
                                            @if ($buku->tingkat !== null)
                                                <span class="rounded-full bg-hutan px-2 py-0.5 font-kode text-[10px] font-medium text-white">
                                                    {{ \App\Models\BukuPerpustakaan::TINGKAT[$buku->tingkat] ?? 'Kelas '.$buku->tingkat }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2.5">
                                            <span class="hidden items-center gap-1 text-xs text-stone-500 sm:inline-flex">
                                                <x-heroicon-o-eye class="size-3.5" />
                                                {{ number_format($buku->jumlah_dibaca) }}
                                            </span>
                                            <a
                                                href="{{ route('perpustakaan.viewer', $buku) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 rounded-full bg-hutan px-3 py-1.5 text-xs font-medium text-white transition hover:bg-hutan-tua"
                                            >
                                                <span>Baca</span>
                                                <x-heroicon-o-book-open class="size-3.5" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($paginator as $buku)
                        <article
                            class="group relative aspect-[3/4] overflow-hidden rounded-2xl border border-garis bg-hutan-tua shadow-xs transition hover:-translate-y-0.5 hover:shadow-lg"
                            @if (! $buku->sampulUrl())
                                data-thumb-pdf="{{ route('perpustakaan.baca', ['buku' => $buku, 'thumb' => 1]) }}"
                                data-thumb-save="{{ route('perpustakaan.sampul-otomatis', $buku) }}"
                            @endif
                        >
                            @if ($buku->sampulUrl())
                                <img src="{{ $buku->sampulUrl() }}" alt="{{ $buku->judul }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="thumb-fallback absolute inset-0 flex flex-col items-center justify-center gap-2 p-3 pb-24 text-center text-white" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 55%, #0f766e 100%);">
                                    <div class="pola-islami-terang pointer-events-none absolute inset-0"></div>
                                    <x-heroicon-o-book-open class="relative size-9 text-amber-300 opacity-95 sm:size-10" />
                                    <span class="relative font-kode text-[10px] tracking-widest text-emerald-100 uppercase">E-Book PDF</span>
                                </div>
                            @endif

                            <span class="absolute top-2 left-2 z-10 rounded-full bg-kertas/95 px-2 py-0.5 font-kode text-[9px] font-medium text-hutan shadow-xs sm:top-2.5 sm:left-2.5 sm:px-2.5 sm:text-[10px]">
                                {{ \App\Models\BukuPerpustakaan::KATEGORI[$buku->kategori] ?? $buku->kategori }}
                            </span>
                            @if ($buku->tingkat !== null)
                                <span class="absolute top-2 right-2 z-10 rounded-full bg-hutan px-2 py-0.5 font-kode text-[9px] font-medium text-white shadow-xs sm:top-2.5 sm:right-2.5 sm:px-2.5 sm:text-[10px]">
                                    {{ \App\Models\BukuPerpustakaan::TINGKAT[$buku->tingkat] ?? 'Kelas '.$buku->tingkat }}
                                </span>
                            @endif

                            <div class="absolute inset-x-0 bottom-0 z-10 flex flex-col justify-end p-3 pt-16 sm:p-4 sm:pt-20" style="background: linear-gradient(to top, rgba(6, 78, 59, 0.94) 0%, rgba(6, 78, 59, 0.76) 55%, rgba(6, 78, 59, 0) 100%);">
                                <h2 class="line-clamp-2 font-judul text-xs leading-snug font-semibold text-white drop-shadow-xs sm:text-base">
                                    {{ $buku->judul }}
                                </h2>
                                <p class="mt-0.5 line-clamp-1 text-[10px] text-emerald-100/90 sm:text-xs">
                                    {{ $buku->penulisRingkas() }}@if ($buku->tahun_terbit) · {{ $buku->tahun_terbit }}@endif
                                </p>
                                @if ($buku->penerbit || $buku->sumberLabel() !== '')
                                    <p class="mt-0.5 line-clamp-1 text-[9px] text-emerald-100/75 sm:text-[11px]">
                                        {{ collect([$buku->penerbit, $buku->sumberLabel()])->filter()->implode(' · ') }}
                                    </p>
                                @endif
                                @if ($buku->mataPelajaran)
                                    <p class="mt-0.5 line-clamp-1 font-kode text-[9px] text-amber-200 sm:text-[11px]">
                                        {{ $buku->mataPelajaran->nama }}
                                    </p>
                                @endif

                                <div class="mt-2 flex items-center justify-between gap-1.5 border-t border-white/20 pt-2 sm:mt-2.5 sm:pt-2.5">
                                    <span class="inline-flex items-center gap-1 text-[10px] text-emerald-100 sm:text-xs">
                                        <x-heroicon-o-eye class="size-3.5" />
                                        <span>{{ number_format($buku->jumlah_dibaca) }}</span><span class="hidden sm:inline"> dibaca</span>
                                    </span>
                                    <a
                                        href="{{ route('perpustakaan.viewer', $buku) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-semibold text-emerald-950 transition hover:bg-amber-300 sm:px-3 sm:py-1.5 sm:text-xs"
                                    >
                                        <span>Baca</span>
                                        <x-heroicon-o-book-open class="size-3 sm:size-3.5" />
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="mt-8">
                {{ $paginator->links() }}
            </div>
        @endif
    </section>

    @if ($modeAktif === 'grid')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (() => {
            const targets = Array.from(document.querySelectorAll('[data-thumb-pdf]'));
            if (targets.length === 0 || typeof pdfjsLib === 'undefined') return;

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            const csrf = @json(csrf_token());
            const queue = [];
            let running = false;

            async function processNext() {
                if (running || queue.length === 0) return;
                running = true;
                const el = queue.shift();
                const pdfUrl = el.dataset.thumbPdf;
                const saveUrl = el.dataset.thumbSave;
                delete el.dataset.thumbPdf;

                try {
                    const pdf = await pdfjsLib.getDocument({
                        url: pdfUrl,
                        rangeChunkSize: 65536,
                        disableAutoFetch: true,
                    }).promise;
                    const page = await pdf.getPage(1);
                    const base = page.getViewport({ scale: 1 });
                    const scale = 360 / base.width;
                    const viewport = page.getViewport({ scale });

                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

                    const dataUrl = canvas.toDataURL('image/jpeg', 0.78);
                    const img = document.createElement('img');
                    img.src = dataUrl;
                    img.alt = 'Sampul buku';
                    img.className = 'absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105';
                    const fallback = el.querySelector('.thumb-fallback');
                    if (fallback) fallback.replaceWith(img);

                    if (saveUrl) {
                        fetch(saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ image: dataUrl }),
                        }).catch(() => {});
                    }
                } catch (_) {
                    // Keep fallback cover if thumbnail generation fails.
                } finally {
                    running = false;
                    processNext();
                }
            }

            const observer = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting && entry.target.dataset.thumbPdf) {
                        observer.unobserve(entry.target);
                        queue.push(entry.target);
                        processNext();
                    }
                }
            }, { rootMargin: '200px' });

            targets.forEach((t) => observer.observe(t));
        })();
    </script>
    @endif
</x-layouts::publik>
