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
            <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($paginator as $buku)
                    <article class="group flex flex-col overflow-hidden rounded-2xl border border-garis bg-white transition hover:-translate-y-0.5 hover:border-hutan/40 hover:shadow-md">
                        <div
                            class="relative aspect-[4/3] overflow-hidden border-b border-garis bg-mint/30"
                            @if (! $buku->sampulUrl())
                                data-thumb-pdf="{{ route('perpustakaan.baca', ['buku' => $buku, 'thumb' => 1]) }}"
                                data-thumb-save="{{ route('perpustakaan.sampul-otomatis', $buku) }}"
                            @endif
                        >
                            @if ($buku->sampulUrl())
                                <img src="{{ $buku->sampulUrl() }}" alt="{{ $buku->judul }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="thumb-fallback relative flex h-full flex-col items-center justify-center gap-2 p-3 text-center text-white" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 55%, #0f766e 100%);">
                                    <div class="pola-islami-terang pointer-events-none absolute inset-0"></div>
                                    <x-heroicon-o-book-open class="relative size-9 text-amber-300 opacity-95 sm:size-10" />
                                    <span class="relative font-kode text-[10px] tracking-widest text-emerald-100 uppercase">E-Book PDF</span>
                                </div>
                            @endif
                            <span class="absolute top-2 left-2 rounded-full bg-kertas/95 px-2 py-0.5 font-kode text-[9px] font-medium text-hutan shadow-xs sm:top-2.5 sm:left-2.5 sm:px-2.5 sm:text-[10px]">
                                {{ \App\Models\BukuPerpustakaan::KATEGORI[$buku->kategori] ?? $buku->kategori }}
                            </span>
                            @if ($buku->tingkat !== null)
                                <span class="absolute top-2 right-2 rounded-full bg-hutan px-2 py-0.5 font-kode text-[9px] font-medium text-white sm:top-2.5 sm:right-2.5 sm:px-2.5 sm:text-[10px]">
                                    {{ \App\Models\BukuPerpustakaan::TINGKAT[$buku->tingkat] ?? 'Kelas '.$buku->tingkat }}
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-3 sm:p-4">
                            <h2 class="line-clamp-2 font-judul text-sm leading-snug font-semibold text-tinta sm:text-lg">
                                {{ $buku->judul }}
                            </h2>
                            <p class="mt-1 line-clamp-1 text-[11px] text-stone-500 sm:text-xs">
                                {{ $buku->penulis ?: 'Tim Penyusun' }}
                                @if ($buku->tahun_terbit)
                                    · {{ $buku->tahun_terbit }}
                                @endif
                            </p>
                            @if ($buku->mataPelajaran)
                                <p class="mt-1 line-clamp-1 font-kode text-[10px] text-hutan sm:mt-1.5 sm:text-[11px]">
                                    {{ $buku->mataPelajaran->nama }}
                                </p>
                            @endif
                            @if ($buku->deskripsi)
                                <p class="mt-1.5 hidden line-clamp-2 text-xs leading-relaxed text-stone-600 sm:block">
                                    {{ $buku->deskripsi }}
                                </p>
                            @endif

                            <div class="mt-auto flex items-center justify-between gap-1.5 border-t border-garis/70 pt-2.5 sm:pt-3">
                                <span class="inline-flex items-center gap-1 text-[11px] text-stone-500 sm:text-xs">
                                    <x-heroicon-o-eye class="size-3.5" />
                                    <span>{{ number_format($buku->jumlah_dibaca) }}</span><span class="hidden sm:inline"> dibaca</span>
                                </span>
                                <a
                                    href="{{ route('perpustakaan.viewer', $buku) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 rounded-full bg-hutan px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-hutan-tua sm:px-3 sm:py-1.5 sm:text-xs"
                                >
                                    <span>Baca</span>
                                    <x-heroicon-o-book-open class="size-3 sm:size-3.5" />
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $paginator->links() }}
            </div>
        @endif
    </section>

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
                    img.className = 'h-full w-full object-cover transition duration-300 group-hover:scale-105';
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
</x-layouts::publik>
