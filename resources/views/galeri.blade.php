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

    $pinsGaleri = $galeriKegiatan->flatMap(function (array $kegiatan): array {
        return collect($kegiatan['media'])->map(fn (array $m, int $idx): array => [
            'galeri_id' => $kegiatan['id'],
            'media_index' => $idx,
            'judul' => $kegiatan['judul'],
            'kategori' => $kegiatan['kategori'],
            'tanggal' => $kegiatan['tanggal'],
            'total_media' => count($kegiatan['media']),
            'jumlah_komentar' => count($kegiatan['komentar']),
            'url' => $m['url'],
            'video' => $m['video'],
        ])->all();
    })->values();
@endphp
<x-layouts::publik
    :title="'Galeri Kegiatan · '.$sekolah['nama']"
    :description="'Dokumentasi foto dan video kegiatan siswa '.$sekolah['nama'].' di '.$sekolah['wilayah'].'.'"
    :tautan="$bagian"
>
    <x-slot:aksi>
        <a href="{{ route('home') }}" class="hidden rounded-full px-3 py-2 text-sm text-stone-600 transition hover:text-tinta sm:inline-flex">Beranda</a>
        <a href="{{ route('ppdb') }}" class="inline-flex items-center gap-1.5 rounded-full bg-hutan px-4 py-2 text-sm font-medium text-white transition hover:bg-hutan-tua">
            SPMB
            <x-heroicon-m-arrow-right class="size-4" />
        </a>
    </x-slot:aksi>

    <section class="border-b border-garis bg-latar/50">
        <header class="flex flex-wrap items-end justify-between gap-6 border-b border-garis bg-kertas px-5 pt-12 pb-8 sm:px-10 lg:px-14 lg:pt-16">
            <div>
                <p class="font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">Dokumentasi Kegiatan Siswa</p>
                <h1 class="mt-2 font-judul text-4xl font-semibold tracking-tight text-tinta sm:text-5xl">Galeri kegiatan</h1>
                <p class="mt-1 font-judul text-xl font-light text-stone-600 sm:text-2xl">momen belajar, prestasi, dan berkarya di {{ $sekolah['nama_singkat'] }}.</p>
            </div>

            @if ($daftarKategori->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('galeri.index') }}"
                        class="rounded-full border px-4 py-1.5 text-xs font-medium transition {{ $kategoriAktif === '' ? 'border-hutan bg-hutan text-white' : 'border-garis bg-kertas text-stone-600 hover:border-hutan/40 hover:text-tinta' }}"
                    >
                        Semua
                    </a>
                    @foreach ($daftarKategori as $kat)
                        <a
                            href="{{ route('galeri.index', ['kategori' => $kat]) }}"
                            class="rounded-full border px-4 py-1.5 text-xs font-medium transition {{ $kategoriAktif === $kat ? 'border-hutan bg-hutan text-white' : 'border-garis bg-kertas text-stone-600 hover:border-hutan/40 hover:text-tinta' }}"
                        >
                            {{ $kat }}
                        </a>
                    @endforeach
                </div>
            @endif
        </header>

        <div class="px-5 py-10 sm:px-10 lg:px-14">
            @if ($pinsGaleri->isNotEmpty())
                {{-- Pinterest Masonry Layout --}}
                <div class="columns-1 gap-4 space-y-4 sm:columns-2 lg:columns-3 xl:columns-4">
                    @foreach ($pinsGaleri as $pin)
                        <article
                            data-pin
                            class="group relative cursor-pointer break-inside-avoid overflow-hidden rounded-2xl border border-garis bg-white shadow-xs transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            onclick="bukaPinGaleri({{ $pin['galeri_id'] }}, {{ $pin['media_index'] }})"
                        >
                            <div class="relative overflow-hidden bg-stone-100">
                                @if ($pin['video'])
                                    <video
                                        src="{{ $pin['url'] }}#t=0.5"
                                        muted
                                        playsinline
                                        preload="metadata"
                                        class="w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                    ></video>
                                    <span class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full bg-tinta/80 px-2.5 py-1 font-kode text-[10px] font-medium text-white backdrop-blur-xs">
                                        <x-heroicon-s-play class="size-3" />
                                        Video
                                    </span>
                                @else
                                    <img
                                        src="{{ $pin['url'] }}"
                                        alt="{{ $pin['judul'] }}"
                                        loading="lazy"
                                        class="w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                    >
                                @endif

                                @if ($pin['total_media'] > 1)
                                    <span class="absolute top-3 left-3 rounded-full bg-black/55 px-2.5 py-0.5 font-kode text-[10px] text-white backdrop-blur-xs">
                                        {{ $pin['media_index'] + 1 }}/{{ $pin['total_media'] }}
                                    </span>
                                @endif

                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition duration-300 group-hover:opacity-100"></div>
                                <div class="pointer-events-none absolute right-3 bottom-3 left-3 flex items-center justify-between text-white opacity-0 transition duration-300 group-hover:opacity-100">
                                    <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-medium backdrop-blur-xs">{{ $pin['kategori'] }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-black/50 px-2.5 py-0.5 text-[11px] backdrop-blur-xs">
                                        <x-heroicon-o-chat-bubble-left class="size-3.5" />
                                        <span data-komentar-count="{{ $pin['galeri_id'] }}">{{ $pin['jumlah_komentar'] }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="p-3.5">
                                <div class="flex items-center justify-between gap-2 font-kode text-[10px] tracking-wider text-stone-400 uppercase">
                                    <span class="text-hutan">{{ $pin['kategori'] }}</span>
                                    <span>{{ $pin['tanggal'] }}</span>
                                </div>
                                <h3 class="mt-1 font-judul text-sm font-semibold leading-snug text-tinta group-hover:text-hutan">
                                    {{ $pin['judul'] }}
                                </h3>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Paginasi --}}
                @if ($paginator->hasPages())
                    <div class="mt-10 border-t border-garis pt-6">
                        {{ $paginator->links() }}
                    </div>
                @endif
            @else
                <div class="rounded-2xl border border-dashed border-garis bg-kertas px-6 py-16 text-center">
                    <x-heroicon-o-photo class="mx-auto size-12 text-stone-400" />
                    <p class="mt-3 font-judul text-lg font-medium text-tinta">Belum ada dokumentasi kegiatan{{ $kategoriAktif !== '' ? ' pada kategori ' . $kategoriAktif : '' }}.</p>
                    <p class="mt-1 text-sm text-stone-500">Foto dan video kegiatan siswa dapat diunggah oleh admin melalui panel AKSARA.</p>
                    @if ($kategoriAktif !== '')
                        <a href="{{ route('galeri.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-garis bg-white px-4 py-2 text-xs font-medium text-tinta transition hover:border-hutan">
                            Lihat semua kategori
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Modal Detail Pin Galeri (Pinterest-style) + Kolom Komentar --}}
    <div id="galeri-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-tinta/75 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-garis bg-kertas shadow-2xl lg:flex-row">
            <button type="button" onclick="tutupPinGaleri()" class="absolute top-3 right-3 z-20 grid size-9 cursor-pointer place-items-center rounded-full bg-white/90 text-tinta shadow-sm transition hover:bg-white" aria-label="Tutup">
                <x-heroicon-m-x-mark class="size-5" />
            </button>

            {{-- Kiri: Media Viewer --}}
            <div class="relative flex min-h-[260px] flex-1 items-center justify-center bg-stone-950 lg:min-h-[520px]">
                <div id="galeri-modal-media" class="flex h-full w-full items-center justify-center p-2"></div>

                <button id="galeri-prev" type="button" onclick="geserMediaGaleri(-1)" class="absolute left-3 hidden size-10 cursor-pointer place-items-center rounded-full bg-white/85 text-tinta shadow-md transition hover:bg-white" aria-label="Sebelumnya">
                    <x-heroicon-m-chevron-left class="size-5" />
                </button>
                <button id="galeri-next" type="button" onclick="geserMediaGaleri(1)" class="absolute right-3 hidden size-10 cursor-pointer place-items-center rounded-full bg-white/85 text-tinta shadow-md transition hover:bg-white" aria-label="Berikutnya">
                    <x-heroicon-m-chevron-right class="size-5" />
                </button>
                <span id="galeri-counter" class="absolute bottom-3 left-1/2 hidden -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 font-kode text-xs text-white backdrop-blur-xs"></span>
            </div>

            {{-- Kanan: Detail Kegiatan & Kolom Komentar --}}
            <div class="flex max-h-[52vh] w-full flex-col border-t border-garis bg-kertas lg:max-h-[92vh] lg:w-[400px] lg:border-t-0 lg:border-l">
                <div class="border-b border-garis p-5">
                    <div class="flex flex-wrap items-center gap-2 font-kode text-[11px] uppercase">
                        <span id="galeri-modal-kategori" class="rounded-full bg-mint px-2.5 py-0.5 text-hutan"></span>
                        <span id="galeri-modal-tanggal" class="text-stone-400"></span>
                    </div>
                    <h3 id="galeri-modal-judul" class="mt-2 font-judul text-xl font-semibold text-tinta"></h3>
                    <p id="galeri-modal-deskripsi" class="mt-2 text-sm leading-relaxed text-stone-600"></p>
                </div>

                {{-- Daftar Komentar --}}
                <div class="flex-1 overflow-y-auto p-5">
                    <h4 class="font-kode text-xs font-semibold tracking-wider text-stone-500 uppercase">
                        Komentar (<span id="galeri-modal-komentar-total">0</span>)
                    </h4>
                    <div id="galeri-komentar-list" class="mt-3 space-y-3"></div>
                </div>

                {{-- Form Tambah Komentar --}}
                <form id="galeri-komentar-form" onsubmit="kirimKomentarGaleri(event)" class="space-y-2.5 border-t border-garis bg-white p-4">
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            id="komentar-nama"
                            type="text"
                            required
                            maxlength="100"
                            placeholder="Nama Anda *"
                            class="w-full rounded-xl border border-garis bg-kertas px-3 py-2 text-xs text-tinta focus:border-hutan focus:outline-none"
                        >
                        <select
                            id="komentar-peran"
                            class="w-full rounded-xl border border-garis bg-kertas px-3 py-2 text-xs text-tinta focus:border-hutan focus:outline-none"
                        >
                            <option value="Siswa">Siswa</option>
                            <option value="Wali Murid">Wali Murid</option>
                            <option value="Guru / Staf">Guru / Staf</option>
                            <option value="Alumni">Alumni</option>
                            <option value="Masyarakat Umum">Masyarakat Umum</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <textarea
                            id="komentar-isi"
                            required
                            rows="2"
                            maxlength="500"
                            placeholder="Tulis komentar atau apresiasi kegiatan..."
                            class="w-full resize-none rounded-xl border border-garis bg-kertas px-3 py-2 text-xs text-tinta focus:border-hutan focus:outline-none"
                        ></textarea>
                        <button
                            id="komentar-submit"
                            type="submit"
                            class="shrink-0 cursor-pointer self-end rounded-xl bg-hutan px-4 py-2 text-xs font-semibold text-white transition hover:bg-hutan-tua disabled:opacity-50"
                        >
                            Kirim
                        </button>
                    </div>
                    <p id="komentar-error" class="hidden text-[11px] text-red-600"></p>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const galeriData = @json($galeriKegiatan->keyBy('id'));
            const csrfToken = @json(csrf_token());
            let activeId = null;
            let activeIndex = 0;

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, (m) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                })[m]);
            }

            function renderMedia() {
                const item = galeriData[activeId];
                if (!item || !item.media || !item.media[activeIndex]) return;

                const media = item.media[activeIndex];
                const box = document.getElementById('galeri-modal-media');
                if (media.video) {
                    box.innerHTML = `<video src="${escapeHtml(media.url)}" controls autoplay playsinline class="max-h-[75vh] w-auto max-w-full rounded-lg"></video>`;
                } else {
                    box.innerHTML = `<img src="${escapeHtml(media.url)}" alt="${escapeHtml(item.judul)}" class="max-h-[75vh] w-auto max-w-full rounded-lg object-contain">`;
                }

                const total = item.media.length;
                const prevBtn = document.getElementById('galeri-prev');
                const nextBtn = document.getElementById('galeri-next');
                const counter = document.getElementById('galeri-counter');

                if (total > 1) {
                    prevBtn.classList.remove('hidden');
                    prevBtn.classList.add('grid');
                    nextBtn.classList.remove('hidden');
                    nextBtn.classList.add('grid');
                    counter.classList.remove('hidden');
                    counter.textContent = `${activeIndex + 1} / ${total}`;
                } else {
                    prevBtn.classList.add('hidden');
                    nextBtn.classList.add('hidden');
                    counter.classList.add('hidden');
                }
            }

            function renderKomentar() {
                const item = galeriData[activeId];
                const list = document.getElementById('galeri-komentar-list');
                const totalEl = document.getElementById('galeri-modal-komentar-total');
                const komentar = item?.komentar || [];

                totalEl.textContent = komentar.length;

                if (komentar.length === 0) {
                    list.innerHTML = `<p class="text-xs italic text-stone-400">Belum ada komentar. Jadilah yang pertama memberi tanggapan!</p>`;
                    return;
                }

                list.innerHTML = komentar.map((k) => `
                    <div class="rounded-xl border border-garis bg-white p-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-tinta">${escapeHtml(k.nama_pengomentar)}</span>
                            <span class="rounded-full bg-mint/60 px-2 py-0.5 font-kode text-[10px] text-hutan">${escapeHtml(k.peran)}</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-stone-700">${escapeHtml(k.isi)}</p>
                        <span class="mt-1 block font-kode text-[10px] text-stone-400">${escapeHtml(k.waktu)}</span>
                    </div>
                `).join('');
            }

            window.bukaPinGaleri = function (galeriId, mediaIndex = 0) {
                const item = galeriData[galeriId];
                if (!item) return;

                activeId = galeriId;
                activeIndex = mediaIndex;

                document.getElementById('galeri-modal-kategori').textContent = item.kategori || 'Umum';
                document.getElementById('galeri-modal-tanggal').textContent = item.tanggal || '';
                document.getElementById('galeri-modal-judul').textContent = item.judul || '';
                document.getElementById('galeri-modal-deskripsi').textContent = item.deskripsi || '';
                document.getElementById('komentar-error').classList.add('hidden');

                renderMedia();
                renderKomentar();

                const modal = document.getElementById('galeri-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            };

            window.tutupPinGaleri = function () {
                const modal = document.getElementById('galeri-modal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.getElementById('galeri-modal-media').innerHTML = '';
                document.body.classList.remove('overflow-hidden');
                activeId = null;
            };

            window.geserMediaGaleri = function (arah) {
                const item = galeriData[activeId];
                if (!item || !item.media?.length) return;
                const len = item.media.length;
                activeIndex = (activeIndex + arah + len) % len;
                renderMedia();
            };

            window.kirimKomentarGaleri = async function (e) {
                e.preventDefault();
                if (!activeId) return;

                const namaEl = document.getElementById('komentar-nama');
                const peranEl = document.getElementById('komentar-peran');
                const isiEl = document.getElementById('komentar-isi');
                const btnEl = document.getElementById('komentar-submit');
                const errEl = document.getElementById('komentar-error');

                errEl.classList.add('hidden');
                btnEl.disabled = true;
                btnEl.textContent = '...';

                try {
                    const res = await fetch(`/galeri/${activeId}/komentar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            nama_pengomentar: namaEl.value,
                            peran: peranEl.value,
                            isi: isiEl.value,
                        }),
                    });

                    const json = await res.json();
                    if (!res.ok) {
                        const msg = json.message || Object.values(json.errors || {})[0]?.[0] || 'Gagal mengirim komentar.';
                        errEl.textContent = msg;
                        errEl.classList.remove('hidden');
                        return;
                    }

                    galeriData[activeId].komentar.unshift(json.komentar);
                    renderKomentar();

                    document.querySelectorAll(`[data-komentar-count="${activeId}"]`).forEach((el) => {
                        el.textContent = galeriData[activeId].komentar.length;
                    });

                    isiEl.value = '';
                } catch (err) {
                    errEl.textContent = 'Terjadi kesalahan jaringan. Coba lagi.';
                    errEl.classList.remove('hidden');
                } finally {
                    btnEl.disabled = false;
                    btnEl.textContent = 'Kirim';
                }
            };

            document.getElementById('galeri-modal')?.addEventListener('click', (e) => {
                if (e.target.id === 'galeri-modal') window.tutupPinGaleri();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && activeId !== null) window.tutupPinGaleri();
            });
        })();
    </script>
</x-layouts::publik>
