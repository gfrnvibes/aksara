<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PPDB · Form Pendaftaran</title>
        @vite(['resources/css/app.css'])
        <style>
            html, body {
                background: #f1f1ef;
                color: #171717;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }
            .motif {
                background-image:
                    radial-gradient(rgb(255 255 255 / 0.22) 1px, transparent 1.2px),
                    linear-gradient(135deg, rgb(255 255 255 / 0.16), transparent 55%);
                background-size: 14px 14px, 100% 100%;
            }
            .wizard-step.hidden { display: none; }
            .input-panel {
                width: 100%;
                border: 1px solid #d4d4d4;
                background: #f7f7f5;
                padding: 0.7rem 0.875rem;
                font-size: 0.875rem;
                color: #404040;
                outline: none;
                transition: border-color 0.2s ease, background 0.2s ease;
                border-radius: 0;
            }
            .input-panel:focus {
                border-color: #059669;
                background: #ffffff;
            }
            .label-form {
                display: block;
                margin-bottom: 0.5rem;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 10px;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                color: #6b7280;
            }
            .card-shell {
                border: 1px solid rgba(163, 163, 163, 0.8);
                background: rgba(255, 255, 255, 0.85);
                box-shadow: 0 12px 28px -18px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(8px);
                border-radius: 0;
            }
        </style>
    </head>
    <body class="min-h-screen text-neutral-900 antialiased selection:bg-emerald-200">
        <svg class="hidden" aria-hidden="true">
            <symbol id="bintang" viewBox="0 0 100 100">
                <rect x="20" y="20" width="60" height="60" />
                <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)" />
            </rect>
        </symbol>
        </svg>

        <header class="sticky top-0 z-40 border-b border-neutral-300/80 bg-[#f1f1ef]/85 backdrop-blur">
            <div class="mx-auto flex h-12 max-w-[1440px] items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-6">
                    <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="Beranda">
                        <svg class="size-6 fill-neutral-900"><use href="#bintang" /></svg>
                        <span class="font-mono text-[11px] font-medium tracking-[0.18em] uppercase text-neutral-900">PPDB</span>
                    </a>
                    <nav class="hidden items-center gap-5 md:flex" aria-label="Navigasi utama">
                        <a href="#form" class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase transition hover:text-neutral-900">Formulir</a>
                        <a href="#cek-status" class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase transition hover:text-neutral-900">Cek Status</a>
                    </nav>
                </div>
                <a href="#form" class="bg-neutral-950 px-3 py-2 font-mono text-[10px] font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">
                    PPDB
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-[1280px] border-x border-neutral-300/70 bg-[#f7f7f5]">
            <section class="border-b border-neutral-300/70 bg-white/60">
                <div class="grid gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1.15fr_0.85fr] lg:py-12">
                    <div>
                        <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">01 / Penerimaan peserta didik baru</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl">
                            Form <span class="text-emerald-700">PPDB</span> <br>
                            calon siswa baru.
                        </h1>
                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-neutral-600">
                            @if ($tahunAjaran)
                                Pendaftaran tahun ajaran <strong>{{ $tahunAjaran->nama }}</strong> dibuka
                                {{ $tahunAjaran->ppdb_ditutup ? 'hingga '.$tahunAjaran->ppdb_ditutup->translatedFormat('d F Y') : 'sekarang' }}.
                                Lengkapi data identitas, alamat, keluarga, dan berkas berikut.
                            @else
                                Pendaftaran sedang ditutup. Anda tetap dapat mengecek status pendaftaran di bawah.
                            @endif
                        </p>
                    </div>

                    <div class="card-shell motif relative overflow-hidden p-5">
                        <div class="absolute -right-8 -bottom-10 size-44 fill-white/10" aria-hidden="true">
                            <svg class="size-44 fill-white/10"><use href="#bintang" /></svg>
                        </div>
                        <div class="relative space-y-3">
                            <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">Panduan</p>
                            <ul class="space-y-2 text-sm text-neutral-700">
                                <li class="flex gap-2"><span class="mt-1.5 size-2 shrink-0 bg-emerald-600"></span>Isi data sesuai identitas asli.</li>
                                <li class="flex gap-2"><span class="mt-1.5 size-2 shrink-0 bg-emerald-600"></span>Gunakan NIK dan NISN yang valid.</li>
                                <li class="flex gap-2"><span class="mt-1.5 size-2 shrink-0 bg-emerald-600"></span>Siapkan foto/scan Kartu Keluarga, Akta Kelahiran, Ijazah/SKL (bila ada), dan pas foto. Maksimal 5 MB per berkas.</li>
                                <li class="flex gap-2"><span class="mt-1.5 size-2 shrink-0 bg-emerald-600"></span>Simpan nomor pendaftaran untuk mengecek status.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section id="form" class="px-4 py-6 sm:px-6 lg:py-8">
                <div class="card-shell p-4 sm:p-6">
                    @if (session('terdaftar'))
                        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4">
                            <p class="font-mono text-[10px] tracking-[0.18em] text-emerald-700 uppercase">Pendaftaran berhasil</p>
                            <p class="mt-2 text-sm text-emerald-900">Terima kasih, <strong>{{ session('terdaftar.nama') }}</strong>. Nomor pendaftaran Anda:</p>
                            <p class="mt-2 font-mono text-2xl font-semibold tracking-wider text-emerald-800">{{ session('terdaftar.nomor') }}</p>
                            <p class="mt-2 text-xs text-emerald-800">Catat atau tangkap layar nomor ini. Gunakan bersama tanggal lahir untuk mengecek status pendaftaran.</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! $tahunAjaran)
                        <div class="border border-neutral-300 bg-white px-4 py-8 text-center">
                            <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">Pendaftaran ditutup</p>
                            <p class="mt-2 text-sm text-neutral-700">Saat ini belum ada periode pendaftaran yang dibuka. Silakan hubungi madrasah untuk informasi jadwal PPDB.</p>
                        </div>
                    @else
                        <form id="formPpdb" method="POST" action="{{ route('ppdb.store') }}" enctype="multipart/form-data" class="space-y-6">
                            @csrf

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="wizard-tab border border-neutral-900 bg-neutral-950 px-3 py-2 font-mono text-[10px] tracking-[0.18em] text-white uppercase transition" data-target="step-1">1. Data Pribadi</button>
                                <button type="button" class="wizard-tab border border-neutral-300 bg-slate-200 px-3 py-2 font-mono text-[10px] tracking-[0.18em] text-slate-700 uppercase transition" data-target="step-2">2. Alamat</button>
                                <button type="button" class="wizard-tab border border-neutral-300 bg-slate-200 px-3 py-2 font-mono text-[10px] tracking-[0.18em] text-slate-700 uppercase transition" data-target="step-3">3. Keluarga</button>
                                <button type="button" class="wizard-tab border border-neutral-300 bg-slate-200 px-3 py-2 font-mono text-[10px] tracking-[0.18em] text-slate-700 uppercase transition" data-target="step-4">4. Berkas</button>
                            </div>

                            <div id="step-1" class="wizard-step space-y-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="nama_lengkap" class="label-form">Nama Lengkap</label>
                                        <input id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="nisn" class="label-form">NISN</label>
                                        <input id="nisn" name="nisn" value="{{ old('nisn') }}" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" title="NISN terdiri dari 10 angka" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="nik" class="label-form">NIK</label>
                                        <input id="nik" name="nik" value="{{ old('nik') }}" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" title="NIK terdiri dari 16 angka" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="tempat_lahir" class="label-form">Tempat Lahir</label>
                                        <input id="tempat_lahir" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="tanggal_lahir" class="label-form">Tanggal Lahir</label>
                                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="input-panel" required>
                                    </div>

                                    <div>
                                        <span class="label-form">Jenis Kelamin</span>
                                        <div class="flex flex-wrap gap-4 border border-neutral-300 bg-[#f7f7f5] px-3 py-2.5 text-sm text-neutral-700">
                                            @foreach (\App\Enums\JenisKelamin::cases() as $jenisKelamin)
                                                <label class="flex items-center gap-2">
                                                    <input type="radio" name="jenis_kelamin" value="{{ $jenisKelamin->value }}" @checked(old('jenis_kelamin') === $jenisKelamin->value) required>
                                                    <span>{{ $jenisKelamin->getLabel() }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <label for="agama" class="label-form">Agama</label>
                                        <select id="agama" name="agama" class="input-panel" required>
                                            @foreach ($pilihanAgama as $agama)
                                                <option value="{{ $agama->value }}" @selected(old('agama', 'islam') === $agama->value)>{{ $agama->getLabel() }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="asal_sekolah" class="label-form">Asal Sekolah (SD/MI)</label>
                                        <input id="asal_sekolah" name="asal_sekolah" value="{{ old('asal_sekolah') }}" placeholder="Contoh: SD Negeri 1 Cisurupan" class="input-panel" required>
                                    </div>
                                </div>
                            </div>

                            <div id="step-2" class="wizard-step hidden space-y-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="alamat" class="label-form">Alamat</label>
                                        <textarea id="alamat" name="alamat" rows="4" class="input-panel" required>{{ old('alamat') }}</textarea>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label for="nomor_telepon" class="label-form">No. HP / WhatsApp Orang Tua</label>
                                        <input id="nomor_telepon" name="nomor_telepon" value="{{ old('nomor_telepon') }}" inputmode="tel" class="input-panel" required>
                                    </div>
                                </div>
                            </div>

                            <div id="step-3" class="wizard-step hidden space-y-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="nama_ayah_kandung" class="label-form">Nama Ayah Kandung</label>
                                        <input id="nama_ayah_kandung" name="nama_ayah_kandung" value="{{ old('nama_ayah_kandung') }}" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="nama_ibu_kandung" class="label-form">Nama Ibu Kandung</label>
                                        <input id="nama_ibu_kandung" name="nama_ibu_kandung" value="{{ old('nama_ibu_kandung') }}" class="input-panel" required>
                                    </div>

                                    <div>
                                        <label for="nama_wali" class="label-form">Nama Wali (bila ada)</label>
                                        <input id="nama_wali" name="nama_wali" value="{{ old('nama_wali') }}" class="input-panel">
                                    </div>

                                    <div>
                                        <label for="nomor_kip_pip" class="label-form">Nomor KIP/PIP (bila ada)</label>
                                        <input id="nomor_kip_pip" name="nomor_kip_pip" value="{{ old('nomor_kip_pip') }}" class="input-panel">
                                    </div>
                                </div>
                            </div>

                            <div id="step-4" class="wizard-step hidden space-y-5">
                                <p class="text-sm text-neutral-600">Foto atau scan yang jelas, format PDF/JPG/PNG, maksimal 5 MB per berkas. Bila ada isian yang salah setelah dikirim, berkas perlu dipilih ulang.</p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    @foreach (\App\Models\Ppdb::BERKAS as $kunci => $judul)
                                        <div>
                                            <label for="berkas_{{ $kunci }}" class="label-form">{{ $judul }}{{ $kunci === 'ijazah' ? ' (bila sudah ada)' : '' }}</label>
                                            <input type="file" id="berkas_{{ $kunci }}" name="berkas[{{ $kunci }}]"
                                                accept="{{ $kunci === 'foto' ? 'image/jpeg,image/png,image/webp' : 'application/pdf,image/jpeg,image/png,image/webp' }}"
                                                class="input-panel file:mr-3 file:border-0 file:bg-neutral-900 file:px-3 file:py-1.5 file:text-xs file:text-white"
                                                @required($kunci !== 'ijazah')>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex items-center justify-between border-t border-neutral-300/80 pt-4">
                                <button type="button" id="prevStep" class="hidden border border-neutral-300 bg-white px-4 py-2 font-mono text-[10px] tracking-[0.18em] uppercase text-neutral-700 transition hover:border-neutral-900">Kembali</button>
                                <div class="ml-auto flex gap-2">
                                    <button type="button" id="nextStep" class="bg-neutral-950 px-4 py-2 font-mono text-[10px] tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">Lanjut</button>
                                    <button type="submit" id="submitStep" class="hidden bg-emerald-700 px-4 py-2 font-mono text-[10px] tracking-[0.18em] text-white uppercase transition hover:bg-emerald-800">Kirim Pendaftaran</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            <section id="cek-status" class="border-t border-neutral-300/70 px-4 py-6 sm:px-6 lg:py-8">
                <div class="card-shell grid gap-6 p-4 sm:p-6 lg:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <p class="font-mono text-[10px] tracking-[0.18em] text-neutral-500 uppercase">02 / Cek status</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">Cek status pendaftaran</h2>
                        <p class="mt-2 text-sm text-neutral-600">Masukkan nomor pendaftaran dan tanggal lahir calon siswa.</p>
                    </div>

                    <div class="space-y-4">
                        @if (session('hasilCek'))
                            @php
                                $warnaHasil = match (session('hasilCek.warna')) {
                                    'success', 'primary' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                                    'info' => 'border-sky-300 bg-sky-50 text-sky-900',
                                    'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
                                    'danger' => 'border-red-300 bg-red-50 text-red-900',
                                    default => 'border-neutral-300 bg-white text-neutral-800',
                                };
                            @endphp
                            <div class="border p-4 {{ $warnaHasil }}">
                                <p class="font-mono text-[10px] tracking-[0.18em] uppercase opacity-70">{{ session('hasilCek.nomor') }} &middot; {{ session('hasilCek.nama') }}</p>
                                <p class="mt-1 text-lg font-semibold">{{ session('hasilCek.status') }}</p>
                                <p class="mt-1 text-sm">{{ session('hasilCek.pesan') }}</p>
                            </div>
                        @endif

                        @if ($errors->cekStatus->any())
                            <div class="border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                {{ $errors->cekStatus->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('ppdb.status') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            @csrf
                            <div>
                                <label for="nomor_cek" class="label-form">Nomor Pendaftaran</label>
                                <input id="nomor_cek" name="nomor" value="{{ old('nomor') }}" placeholder="PPDB-2027-001" class="input-panel uppercase" required>
                            </div>
                            <div>
                                <label for="tanggal_lahir_cek" class="label-form">Tanggal Lahir</label>
                                <input type="date" id="tanggal_lahir_cek" name="tanggal_lahir_cek" value="{{ old('tanggal_lahir_cek') }}" class="input-panel" required>
                            </div>
                            <button type="submit" class="bg-neutral-950 px-4 py-3 font-mono text-[10px] tracking-[0.18em] text-white uppercase transition hover:bg-neutral-700">Cek</button>
                        </form>
                    </div>
                </div>
            </section>
        </main>

        @if ($tahunAjaran)
            <script>
                const steps = ['step-1', 'step-2', 'step-3', 'step-4'];
                const tabs = document.querySelectorAll('.wizard-tab');
                const prevBtn = document.getElementById('prevStep');
                const nextBtn = document.getElementById('nextStep');
                const submitBtn = document.getElementById('submitStep');
                let currentStep = 0;

                function showStep(index) {
                    currentStep = index;
                    steps.forEach((step, idx) => {
                        const el = document.getElementById(step);
                        if (el) el.classList.toggle('hidden', idx !== index);
                    });

                    tabs.forEach((tab, idx) => {
                        const active = idx === index;
                        tab.classList.toggle('bg-neutral-950', active);
                        tab.classList.toggle('text-white', active);
                        tab.classList.toggle('bg-slate-200', !active);
                        tab.classList.toggle('text-slate-700', !active);
                        tab.classList.toggle('border-neutral-900', active);
                        tab.classList.toggle('border-neutral-300', !active);
                    });

                    prevBtn.classList.toggle('hidden', index === 0);
                    nextBtn.classList.toggle('hidden', index === steps.length - 1);
                    submitBtn.classList.toggle('hidden', index !== steps.length - 1);
                }

                prevBtn.addEventListener('click', () => showStep(Math.max(0, currentStep - 1)));
                nextBtn.addEventListener('click', () => showStep(Math.min(steps.length - 1, currentStep + 1)));
                tabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        const index = steps.indexOf(tab.dataset.target);
                        if (index >= 0) showStep(index);
                    });
                });

                // A required field left empty on another step: open that step so the browser can point at it.
                document.getElementById('formPpdb').addEventListener('invalid', (event) => {
                    const step = event.target.closest('.wizard-step');
                    const index = step ? steps.indexOf(step.id) : -1;
                    if (index >= 0 && index !== currentStep) showStep(index);
                }, true);

                showStep(0);
            </script>
        @endif
    </body>
</html>
