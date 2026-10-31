@props([
    'title',
    'description' => null,
    'tautan' => [],
])
@php
    $sekolah = config('sekolah');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <meta name="theme-color" content="#0f7a4f">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>document.documentElement.classList.add('js')</script>
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        /* Dotted paper behind the framed page, as on filamentphp.com. */
        .latar-titik {
            background-image: radial-gradient(rgb(19 48 31 / 0.13) 1px, transparent 1.3px);
            background-size: 18px 18px;
        }

        /* Interlocking eight-pointed stars, drifting slowly behind a section. */
        .pola-islami {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cg fill='none' stroke='%230f7a4f' stroke-opacity='.16'%3E%3Crect x='20' y='20' width='32' height='32'/%3E%3Crect x='20' y='20' width='32' height='32' transform='rotate(45 36 36)'/%3E%3Ccircle cx='36' cy='36' r='6'/%3E%3Cpath d='M0 36h13M59 36h13M36 0v13M36 59v13'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 72px 72px;
            animation: pola-geser 24s linear infinite;
            mask-image: radial-gradient(ellipse at center, black 25%, transparent 72%);
        }
        .pola-islami-terang {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.12'%3E%3Crect x='20' y='20' width='32' height='32'/%3E%3Crect x='20' y='20' width='32' height='32' transform='rotate(45 36 36)'/%3E%3Ccircle cx='36' cy='36' r='6'/%3E%3Cpath d='M0 36h13M59 36h13M36 0v13M36 59v13'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 72px 72px;
            animation: pola-geser 30s linear infinite reverse;
        }
        @keyframes pola-geser { to { background-position: 72px 72px; } }

        /* Floating stars and the slowly turning logo. */
        .melayang { animation: melayang 7s ease-in-out infinite; animation-delay: var(--jeda, 0s); }
        @keyframes melayang {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-14px) rotate(22deg); }
        }
        .putar-lambat { animation: putar 18s linear infinite; }
        @keyframes putar { to { transform: rotate(360deg); } }

        /* A pill button that keeps breathing, and a badge with a passing shine. */
        .denyut { position: relative; isolation: isolate; }
        .denyut::before {
            content: ''; position: absolute; inset: 0; z-index: -1; border-radius: inherit; background: inherit;
            animation: denyut 2.6s cubic-bezier(.2, .6, .3, 1) infinite;
        }
        @keyframes denyut {
            0% { transform: scale(1); opacity: .55; }
            70%, 100% { transform: scale(1.18, 1.45); opacity: 0; }
        }
        .kilau { position: relative; overflow: hidden; }
        .kilau::after {
            content: ''; position: absolute; inset: 0 auto 0 -60%; width: 50%; transform: skewX(-20deg);
            background: linear-gradient(90deg, transparent, rgb(255 255 255 / .75), transparent);
            animation: kilau 3.6s ease-in-out infinite;
        }
        @keyframes kilau { 0%, 55% { left: -60%; } 100% { left: 130%; } }

        /* Navigation links framed by two corner brackets on hover. */
        .tautan-nav { position: relative; }
        .tautan-nav::before, .tautan-nav::after {
            content: ''; position: absolute; width: 7px; height: 7px; opacity: 0; transition: all .25s cubic-bezier(.2, .8, .3, 1);
        }
        .tautan-nav::before { top: 0; left: 0; border-top: 2px solid var(--color-emas); border-left: 2px solid var(--color-emas); transform: translate(-6px, -6px); }
        .tautan-nav::after { right: 0; bottom: 0; border-right: 2px solid var(--color-hutan); border-bottom: 2px solid var(--color-hutan); transform: translate(6px, 6px); }
        .tautan-nav:hover::before, .tautan-nav:hover::after, .tautan-nav:focus-visible::before, .tautan-nav:focus-visible::after { opacity: 1; transform: translate(0, 0); }

        /* Endless strip of words. */
        .marquee { mask-image: linear-gradient(90deg, transparent, black 8%, black 92%, transparent); }
        .marquee-jalur { animation: marquee var(--durasi, 45s) linear infinite; }
        .marquee:hover .marquee-jalur { animation-play-state: paused; }
        @keyframes marquee { to { transform: translateX(-50%); } }

        /* Headline lines sliding up into place, and blocks rising as they scroll into view. */
        .baris-judul { display: block; overflow: hidden; padding-bottom: .08em; }
        .baris-judul > span { display: inline-block; animation: baris-naik .9s cubic-bezier(.2, .8, .2, 1) both; animation-delay: var(--jeda, 0s); }
        @keyframes baris-naik { from { transform: translateY(105%); } to { transform: translateY(0); } }
        .js [data-muncul] { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2, .8, .2, 1); transition-delay: var(--jeda, 0s); }
        .js [data-muncul='kiri'] { transform: translateX(-24px); }
        .js [data-muncul='skala'] { transform: scale(.94); }
        .js [data-muncul].tampil { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .js [data-muncul] { opacity: 1; transform: none; }
        }
    </style>
    {{ $kepala ?? '' }}
</head>
<body class="latar-titik min-h-screen bg-latar font-teks text-stone-600 antialiased selection:bg-mint selection:text-tinta sm:px-5 lg:px-10">
    <svg class="hidden" aria-hidden="true">
        <symbol id="bintang" viewBox="0 0 100 100">
            <rect x="20" y="20" width="60" height="60" />
            <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)" />
        </symbol>
        <symbol id="tambah" viewBox="0 0 14 14">
            <path d="M7 0v14M0 7h14" stroke="currentColor" stroke-width="1.5" />
        </symbol>
    </svg>

    <div class="mx-auto min-h-screen w-full max-w-[1400px] overflow-x-clip border-x border-b border-garis bg-kertas">
        <header class="sticky top-0 z-50 border-b border-garis bg-kertas/90 backdrop-blur">
            <div class="flex h-16 items-stretch">
                <a href="{{ route('home') }}" class="group flex items-center gap-2.5 border-r border-garis px-4 sm:px-6" aria-label="{{ $sekolah['nama'] }}">
                    <svg class="putar-lambat size-7 fill-hutan transition group-hover:fill-emas"><use href="#bintang" /></svg>
                    <span class="font-judul text-lg leading-none font-semibold tracking-tight text-tinta">
                        {{ $sekolah['nama_singkat'] }}
                        <span class="block font-kode text-[9px] font-normal tracking-[0.2em] text-stone-500 uppercase">{{ $sekolah['jenjang'] }}</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 px-4 md:flex" aria-label="Navigasi utama">
                    @foreach ($tautan as $url => $label)
                        <a href="{{ $url }}" class="tautan-nav px-2.5 py-1.5 text-[15px] transition hover:text-tinta">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-2 border-l border-garis px-3 sm:px-5">
                    {{ $aksi ?? '' }}
                    @if ($tautan !== [])
                        <details class="relative md:hidden">
                            <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-full border border-garis text-tinta [&::-webkit-details-marker]:hidden" aria-label="Menu">
                                <x-heroicon-o-bars-3 class="size-5" />
                            </summary>
                            <div class="absolute right-0 mt-2 w-52 rounded-lg border border-garis bg-kertas p-1.5 shadow-lg shadow-tinta/10">
                                @foreach ($tautan as $url => $label)
                                    <a href="{{ $url }}" class="block rounded-md px-3 py-2 text-sm hover:bg-mint/60 hover:text-tinta">{{ $label }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </div>
        </header>

        {{ $slot }}

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-garis px-4 py-4 font-kode text-[11px] tracking-[0.15em] text-stone-400 uppercase sm:px-6">
            <span>© {{ now()->year }} {{ $sekolah['nama'] }}</span>
            <span class="flex items-center gap-2">
                <svg class="size-3 fill-emas"><use href="#bintang" /></svg>
                Berilmu · Berakhlak
            </span>
        </div>
    </div>

    <script>
        (() => {
            const muncul = new IntersectionObserver((entri) => {
                entri.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add('tampil');
                        muncul.unobserve(e.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            document.querySelectorAll('[data-muncul]').forEach((el) => muncul.observe(el));

            // Numbers count up from zero the first time they are seen.
            const hitung = new IntersectionObserver((entri) => {
                entri.forEach((e) => {
                    if (! e.isIntersecting) return;
                    hitung.unobserve(e.target);
                    const akhir = Number(e.target.dataset.hitung);
                    const mulai = performance.now();
                    const langkah = (waktu) => {
                        const t = Math.min((waktu - mulai) / 1400, 1);
                        e.target.textContent = Math.round(akhir * (1 - Math.pow(1 - t, 3)));
                        if (t < 1) requestAnimationFrame(langkah);
                    };
                    requestAnimationFrame(langkah);
                });
            });
            if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.querySelectorAll('[data-hitung]').forEach((el) => hitung.observe(el));
            }

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
        })();
    </script>
    {{ $skrip ?? '' }}
</body>
</html>
