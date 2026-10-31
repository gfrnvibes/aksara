@php
    $sekolah = config('sekolah');
    $langkah = ['Data Pribadi', 'Alamat', 'Keluarga', 'Berkas'];
    $isianPerLangkah = [
        ['nama_lengkap', 'nisn', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'asal_sekolah'],
        ['alamat', 'nomor_telepon'],
        ['nama_ayah_kandung', 'nama_ibu_kandung', 'nama_wali', 'nomor_kip_pip'],
    ];
    $langkahAwal = collect($isianPerLangkah)->search(fn (array $isian): bool => $errors->hasAny($isian));
    $langkahAwal = $langkahAwal === false ? ($errors->has('berkas.*') ? 3 : 0) : $langkahAwal;
@endphp
<x-layouts::publik
    :title="'SPMB · Sistem Penerimaan Murid Baru · '.$sekolah['nama']"
    :description="'Pendaftaran murid baru '.$sekolah['nama'].' secara daring: isi formulir, unggah berkas, dan cek status pendaftaran.'"
    :tautan="[route('home') => 'Beranda', '#form' => 'Formulir', '#cek-status' => 'Cek Status']"
>
    <x-slot:kepala>
        <style>
            .isian {
                width: 100%; border-radius: .625rem; border: 1px solid var(--color-garis); background: #fff;
                padding: .7rem .9rem; font-size: .95rem; color: var(--color-tinta); outline: none;
                transition: border-color .2s ease, box-shadow .2s ease;
            }
            .isian:focus { border-color: var(--color-hutan); box-shadow: 0 0 0 4px rgb(15 122 79 / .12); }
            .isian::placeholder { color: #a8a29e; }
            .label-isian { display: block; margin-bottom: .4rem; font-size: .875rem; font-weight: 500; color: var(--color-tinta); }
            .langkah-panel.aktif { animation: panel-masuk .45s cubic-bezier(.2, .8, .2, 1); }
            @keyframes panel-masuk { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: none; } }
            .centang path { stroke-dasharray: 30; stroke-dashoffset: 30; animation: centang .6s .3s ease forwards; }
            @keyframes centang { to { stroke-dashoffset: 0; } }
        </style>
    </x-slot:kepala>

    <x-slot:aksi>
        <a href="#cek-status" class="hidden rounded-full px-3 py-2 text-sm text-stone-600 transition hover:text-tinta sm:inline-flex">Cek status</a>
        @if ($tahunAjaran)
            <a href="#form" class="inline-flex items-center gap-1.5 rounded-full bg-hutan px-4 py-2 text-sm font-medium text-white transition hover:bg-hutan-tua">
                Daftar
                <x-heroicon-m-arrow-down class="size-4" />
            </a>
        @endif
    </x-slot:aksi>

    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-garis">
        <div class="pola-islami pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <svg class="melayang pointer-events-none absolute top-12 left-[9%] size-9 fill-emas/50" style="--jeda: -2s" aria-hidden="true"><use href="#bintang" /></svg>
        <svg class="melayang pointer-events-none absolute right-[11%] bottom-14 size-12 fill-hutan/25" style="--jeda: -4s" aria-hidden="true"><use href="#bintang" /></svg>

        <div class="relative px-4 py-16 text-center sm:px-6 sm:py-20">
            @if ($tahunAjaran)
                <p class="kilau inline-flex items-center gap-2 rounded-full border border-emas/40 bg-emas-muda/70 py-1 pr-3 pl-1 text-sm text-tinta" data-muncul>
                    <span class="rounded-full bg-hutan px-2 py-0.5 text-xs font-medium text-white">Dibuka</span>
                    Tahun ajaran {{ $tahunAjaran->nama }}{{ $tahunAjaran->ppdb_ditutup ? ' · hingga '.$tahunAjaran->ppdb_ditutup->translatedFormat('d F Y') : '' }}
                </p>
            @else
                <p class="inline-flex items-center gap-2 rounded-full border border-garis bg-kertas px-3 py-1 text-sm text-stone-500" data-muncul>
                    <span class="size-2 rounded-full bg-stone-400"></span>
                    Pendaftaran sedang ditutup
                </p>
            @endif

            <h1 class="mx-auto mt-6 max-w-3xl font-judul text-4xl leading-[1.05] tracking-tight text-tinta sm:text-6xl">
                <span class="baris-judul font-semibold"><span>Sistem Penerimaan</span></span>
                <span class="baris-judul font-light"><span style="--jeda: .15s">Murid Baru <span class="font-medium text-hutan">(SPMB)</span></span></span>
            </h1>
            <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed" data-muncul style="--jeda: .25s">
                @if ($tahunAjaran)
                    Daftarkan calon murid kelas VII {{ $sekolah['nama'] }} dalam empat langkah singkat, lalu pantau statusnya dengan nomor pendaftaran.
                @else
                    Belum ada periode pendaftaran yang dibuka. Anda tetap dapat mengecek status pendaftaran di bawah.
                @endif
            </p>
        </div>

        <div class="relative grid border-t border-garis bg-kertas/80 backdrop-blur-sm sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['ikon' => 'heroicon-o-identification', 'judul' => 'Data sesuai identitas', 'isi' => 'NIK dan NISN sesuai Kartu Keluarga dan Dapodik.'],
                ['ikon' => 'heroicon-o-document-arrow-up', 'judul' => 'Siapkan berkas', 'isi' => 'KK, akta kelahiran, pas foto, dan ijazah/SKL bila ada. Maks. 5 MB.'],
                ['ikon' => 'heroicon-o-ticket', 'judul' => 'Simpan nomor', 'isi' => 'Nomor pendaftaran muncul setelah formulir terkirim.'],
                ['ikon' => 'heroicon-o-magnifying-glass', 'judul' => 'Cek status', 'isi' => 'Pakai nomor pendaftaran dan tanggal lahir calon murid.'],
            ] as $panduan)
                <div class="flex gap-3 border-garis p-5 text-left not-last:border-b sm:nth-odd:border-r lg:not-last:border-r lg:not-last:border-b-0" data-muncul style="--jeda: {{ $loop->index * 80 }}ms">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-mint/60 text-hutan">
                        <x-dynamic-component :component="$panduan['ikon']" class="size-5" />
                    </span>
                    <span>
                        <span class="block font-judul font-semibold text-tinta">{{ $panduan['judul'] }}</span>
                        <span class="mt-0.5 block text-sm leading-relaxed">{{ $panduan['isi'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Formulir --}}
    <section id="form" class="scroll-mt-16 border-b border-garis px-4 py-12 sm:px-10 lg:px-14 lg:py-16">
        <div class="mx-auto max-w-4xl">
            @if (session('terdaftar'))
                <div class="mb-8 overflow-hidden rounded-2xl border border-hutan/30 bg-mint/50 p-6 text-center sm:p-8" data-muncul="skala">
                    <svg class="centang mx-auto size-14 text-hutan" viewBox="0 0 52 52" fill="none" aria-hidden="true">
                        <circle cx="26" cy="26" r="24" stroke="currentColor" stroke-width="2" opacity=".25" />
                        <path d="M15 27l7 7 15-16" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="mt-4 font-judul text-2xl font-semibold text-tinta">Pendaftaran berhasil</p>
                    <p class="mt-1">Terima kasih, <strong class="text-tinta">{{ session('terdaftar.nama') }}</strong>. Nomor pendaftaran Anda:</p>
                    <p class="mx-auto mt-4 w-fit rounded-xl border border-dashed border-hutan/50 bg-white px-6 py-3 font-kode text-2xl font-medium tracking-wider text-hutan">{{ session('terdaftar.nomor') }}</p>
                    <p class="mt-4 text-sm">Catat atau tangkap layar nomor ini. Gunakan bersama tanggal lahir untuk mengecek status pendaftaran.</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-medium">Periksa kembali isian berikut:</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $tahunAjaran)
                <div class="rounded-2xl border border-garis bg-white px-6 py-12 text-center" data-muncul>
                    <x-heroicon-o-calendar-days class="mx-auto size-10 text-stone-400" />
                    <p class="mt-3 font-judul text-xl font-semibold text-tinta">Pendaftaran ditutup</p>
                    <p class="mt-1">Saat ini belum ada periode pendaftaran yang dibuka. Silakan hubungi madrasah untuk informasi jadwal SPMB.</p>
                </div>
            @else
                <header class="mb-8 text-center" data-muncul>
                    <p class="font-kode text-[11px] tracking-[0.18em] text-hutan uppercase">Formulir pendaftaran</p>
                    <h2 class="mt-2 font-judul text-3xl font-semibold tracking-tight text-tinta sm:text-4xl">Data calon murid</h2>
                </header>

                <form id="formPpdb" data-langkah-awal="{{ $langkahAwal }}" method="POST" action="{{ route('ppdb.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-garis bg-white p-5 shadow-xl shadow-tinta/5 sm:p-8" data-muncul="skala">
                    @csrf

                    <ol class="relative grid grid-cols-4 gap-2" aria-label="Langkah pendaftaran">
                        <span class="absolute top-4 right-[12.5%] left-[12.5%] h-0.5 rounded-full bg-garis" aria-hidden="true"></span>
                        <span id="garisKemajuan" class="absolute top-4 left-[12.5%] h-0.5 w-0 rounded-full bg-hutan transition-all duration-500" aria-hidden="true"></span>
                        @foreach ($langkah as $judulLangkah)
                            <li class="relative">
                                <button type="button" class="langkah-tab group flex w-full flex-col items-center gap-2" data-target="step-{{ $loop->iteration }}">
                                    <span class="langkah-bulat grid size-8 place-items-center rounded-full border-2 border-garis bg-white font-kode text-sm text-stone-400 transition duration-300">{{ $loop->iteration }}</span>
                                    <span class="langkah-label text-xs text-stone-500 transition sm:text-sm">{{ $judulLangkah }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-8">
                        <div id="step-1" class="langkah-panel"><div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="nama_lengkap" class="label-isian">Nama lengkap</label>
                                <input id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="isian" autocomplete="name" required>
                            </div>
                            <div>
                                <label for="nisn" class="label-isian">NISN</label>
                                <input id="nisn" name="nisn" value="{{ old('nisn') }}" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="NISN terdiri dari 10 angka" placeholder="10 angka" class="isian" required>
                            </div>
                            <div>
                                <label for="nik" class="label-isian">NIK</label>
                                <input id="nik" name="nik" value="{{ old('nik') }}" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" title="NIK terdiri dari 16 angka" placeholder="16 angka, sesuai KK" class="isian" required>
                            </div>
                            <div>
                                <label for="tempat_lahir" class="label-isian">Tempat lahir</label>
                                <input id="tempat_lahir" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="isian" required>
                            </div>
                            <div>
                                <label for="tanggal_lahir" class="label-isian">Tanggal lahir</label>
                                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="isian" required>
                            </div>
                            <div>
                                <span class="label-isian">Jenis kelamin</span>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach (\App\Enums\JenisKelamin::cases() as $jenisKelamin)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-garis px-3 py-2.5 text-sm text-tinta transition has-checked:border-hutan has-checked:bg-mint/50">
                                            <input type="radio" name="jenis_kelamin" value="{{ $jenisKelamin->value }}" class="accent-hutan" @checked(old('jenis_kelamin') === $jenisKelamin->value) required>
                                            {{ $jenisKelamin->getLabel() }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label for="agama" class="label-isian">Agama</label>
                                <select id="agama" name="agama" class="isian" required>
                                    @foreach ($pilihanAgama as $agama)
                                        <option value="{{ $agama->value }}" @selected(old('agama', 'islam') === $agama->value)>{{ $agama->getLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="asal_sekolah" class="label-isian">Asal sekolah (SD/MI)</label>
                                <input id="asal_sekolah" name="asal_sekolah" value="{{ old('asal_sekolah') }}" placeholder="Contoh: SD Negeri 1 Cisurupan" class="isian" required>
                            </div>
                        </div>
                        </div>

                        <div id="step-2" class="langkah-panel hidden"><div class="grid gap-5">
                            <div>
                                <label for="alamat" class="label-isian">Alamat lengkap</label>
                                <textarea id="alamat" name="alamat" rows="4" placeholder="Kampung/jalan, RT/RW, desa, kecamatan" class="isian" required>{{ old('alamat') }}</textarea>
                            </div>
                            <div>
                                <label for="nomor_telepon" class="label-isian">No. HP / WhatsApp orang tua</label>
                                <input id="nomor_telepon" name="nomor_telepon" value="{{ old('nomor_telepon') }}" inputmode="tel" placeholder="08xxxxxxxxxx" class="isian" required>
                            </div>
                        </div>
                        </div>

                        <div id="step-3" class="langkah-panel hidden"><div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="nama_ayah_kandung" class="label-isian">Nama ayah kandung</label>
                                <input id="nama_ayah_kandung" name="nama_ayah_kandung" value="{{ old('nama_ayah_kandung') }}" class="isian" required>
                            </div>
                            <div>
                                <label for="nama_ibu_kandung" class="label-isian">Nama ibu kandung</label>
                                <input id="nama_ibu_kandung" name="nama_ibu_kandung" value="{{ old('nama_ibu_kandung') }}" class="isian" required>
                            </div>
                            <div>
                                <label for="nama_wali" class="label-isian">Nama wali <span class="font-normal text-stone-400">(bila ada)</span></label>
                                <input id="nama_wali" name="nama_wali" value="{{ old('nama_wali') }}" class="isian">
                            </div>
                            <div>
                                <label for="nomor_kip_pip" class="label-isian">Nomor KIP/PIP <span class="font-normal text-stone-400">(bila ada)</span></label>
                                <input id="nomor_kip_pip" name="nomor_kip_pip" value="{{ old('nomor_kip_pip') }}" class="isian">
                            </div>
                        </div>
                        </div>

                        <div id="step-4" class="langkah-panel hidden">
                            <p class="text-sm">Foto atau scan yang jelas, format PDF/JPG/PNG, maksimal 5 MB per berkas. Bila ada isian yang salah setelah dikirim, berkas perlu dipilih ulang.</p>
                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                @foreach (\App\Models\Ppdb::BERKAS as $kunci => $judul)
                                    <label for="berkas_{{ $kunci }}" class="group relative flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed border-garis p-4 transition hover:border-hutan/50 hover:bg-mint/30 [&.terisi]:border-solid [&.terisi]:border-hutan/50 [&.terisi]:bg-mint/30">
                                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-mint/60 text-hutan transition group-hover:scale-110">
                                            <x-dynamic-component :component="$kunci === 'foto' ? 'heroicon-o-camera' : 'heroicon-o-document-arrow-up'" class="size-5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-tinta">{{ $judul }}{!! $kunci === 'ijazah' ? ' <span class="font-normal text-stone-400">(bila sudah ada)</span>' : '' !!}</span>
                                            <span class="nama-berkas block truncate text-sm text-stone-500">Klik untuk memilih berkas</span>
                                        </span>
                                        <input type="file" id="berkas_{{ $kunci }}" name="berkas[{{ $kunci }}]"
                                            accept="{{ $kunci === 'foto' ? 'image/jpeg,image/png,image/webp' : 'application/pdf,image/jpeg,image/png,image/webp' }}"
                                            class="absolute inset-0 cursor-pointer opacity-0"
                                            @required($kunci !== 'ijazah')>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between border-t border-garis pt-5">
                        <button type="button" id="prevStep" class="invisible inline-flex items-center gap-1.5 rounded-full border border-garis px-5 py-2.5 text-sm text-tinta transition hover:border-hutan/40">
                            <x-heroicon-m-arrow-left class="size-4" />
                            Kembali
                        </button>
                        <button type="button" id="nextStep" class="inline-flex items-center gap-1.5 rounded-full bg-tinta px-5 py-2.5 text-sm font-medium text-white transition hover:bg-hutan">
                            Lanjut
                            <x-heroicon-m-arrow-right class="size-4" />
                        </button>
                        <button type="submit" id="submitStep" class="denyut hidden items-center gap-1.5 rounded-full bg-hutan px-6 py-2.5 text-sm font-medium text-white transition hover:bg-hutan-tua">
                            Kirim pendaftaran
                            <x-heroicon-m-paper-airplane class="size-4" />
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </section>
    <x-publik.sambungan />

    {{-- Cek status --}}
    <section id="cek-status" class="relative scroll-mt-16 overflow-hidden bg-tinta px-4 py-14 text-white/80 sm:px-10 lg:px-14 lg:py-20">
        <div class="pola-islami-terang pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-5xl gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div data-muncul="kiri">
                <p class="font-kode text-[11px] tracking-[0.18em] text-emas-muda uppercase">Cek status</p>
                <h2 class="mt-2 font-judul text-3xl font-semibold tracking-tight text-white sm:text-4xl">Sudah mendaftar?</h2>
                <p class="mt-2">Masukkan nomor pendaftaran dan tanggal lahir calon murid untuk melihat hasil verifikasi dan seleksi.</p>
            </div>

            <div class="space-y-4" data-muncul style="--jeda: .1s">
                @if (session('hasilCek'))
                    @php
                        $warnaHasil = match (session('hasilCek.warna')) {
                            'success', 'primary' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                            'info' => 'border-sky-300 bg-sky-50 text-sky-900',
                            'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
                            'danger' => 'border-red-300 bg-red-50 text-red-900',
                            default => 'border-stone-300 bg-white text-stone-800',
                        };
                    @endphp
                    <div class="rounded-xl border p-4 {{ $warnaHasil }}">
                        <p class="font-kode text-xs tracking-wider opacity-70">{{ session('hasilCek.nomor') }} · {{ session('hasilCek.nama') }}</p>
                        <p class="mt-1 font-judul text-xl font-semibold">{{ session('hasilCek.status') }}</p>
                        <p class="mt-1 text-sm">{{ session('hasilCek.pesan') }}</p>
                    </div>
                @endif

                @if ($errors->cekStatus->any())
                    <div class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->cekStatus->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('ppdb.status') }}" class="grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <div>
                        <label for="nomor_cek" class="label-isian">Nomor pendaftaran</label>
                        <input id="nomor_cek" name="nomor" value="{{ old('nomor') }}" placeholder="SPMB-2027-001" class="isian uppercase" required>
                    </div>
                    <div>
                        <label for="tanggal_lahir_cek" class="label-isian">Tanggal lahir</label>
                        <input type="date" id="tanggal_lahir_cek" name="tanggal_lahir_cek" value="{{ old('tanggal_lahir_cek') }}" class="isian" required>
                    </div>
                    <button type="submit" class="inline-flex h-[46px] items-center justify-center gap-1.5 rounded-full bg-hutan px-5 text-sm font-medium text-white transition hover:bg-hutan-tua">
                        <x-heroicon-m-magnifying-glass class="size-4" />
                        Cek
                    </button>
                </form>
            </div>
        </div>
    </section>

    @if ($tahunAjaran)
        <x-slot:skrip>
            <script>
                (() => {
                    const form = document.getElementById('formPpdb');
                    const panel = [...form.querySelectorAll('.langkah-panel')];
                    const tab = [...form.querySelectorAll('.langkah-tab')];
                    const garis = document.getElementById('garisKemajuan');
                    const kembali = document.getElementById('prevStep');
                    const lanjut = document.getElementById('nextStep');
                    const kirim = document.getElementById('submitStep');
                    let aktif = 0;

                    const tampilkan = (indeks) => {
                        aktif = indeks;
                        panel.forEach((el, i) => {
                            el.classList.toggle('hidden', i !== indeks);
                            el.classList.toggle('aktif', i === indeks);
                        });
                        tab.forEach((el, i) => {
                            const bulat = el.querySelector('.langkah-bulat');
                            const label = el.querySelector('.langkah-label');
                            bulat.classList.toggle('border-hutan', i <= indeks);
                            bulat.classList.toggle('bg-hutan', i < indeks);
                            bulat.classList.toggle('text-white', i < indeks);
                            bulat.classList.toggle('text-hutan', i === indeks);
                            bulat.classList.toggle('scale-110', i === indeks);
                            label.classList.toggle('text-tinta', i === indeks);
                            label.classList.toggle('font-medium', i === indeks);
                        });
                        garis.style.width = (indeks / (panel.length - 1)) * 75 + '%';
                        kembali.classList.toggle('invisible', indeks === 0);
                        lanjut.classList.toggle('hidden', indeks === panel.length - 1);
                        kirim.classList.toggle('hidden', indeks !== panel.length - 1);
                        kirim.classList.toggle('inline-flex', indeks === panel.length - 1);
                    };

                    // Moving on checks only the fields of the current step.
                    lanjut.addEventListener('click', () => {
                        const kosong = [...panel[aktif].querySelectorAll('input, select, textarea')].find((el) => ! el.checkValidity());
                        if (kosong) {
                            kosong.reportValidity();
                            return;
                        }
                        tampilkan(Math.min(panel.length - 1, aktif + 1));
                        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    kembali.addEventListener('click', () => tampilkan(Math.max(0, aktif - 1)));
                    tab.forEach((el, i) => el.addEventListener('click', () => tampilkan(i)));

                    // A required field left empty on another step: open that step so the browser can point at it.
                    form.addEventListener('invalid', (event) => {
                        const indeks = panel.indexOf(event.target.closest('.langkah-panel'));
                        if (indeks >= 0 && indeks !== aktif) tampilkan(indeks);
                    }, true);

                    form.querySelectorAll('input[type=file]').forEach((input) => {
                        input.addEventListener('change', () => {
                            const label = input.closest('label');
                            label.classList.toggle('terisi', input.files.length > 0);
                            label.querySelector('.nama-berkas').textContent = input.files[0]?.name ?? 'Klik untuk memilih berkas';
                        });
                    });

                    tampilkan(Number(form.dataset.langkahAwal ?? 0));
                })();
            </script>
        </x-slot:skrip>
    @endif
</x-layouts::publik>
