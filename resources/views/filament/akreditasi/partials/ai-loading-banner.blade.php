<div
    x-data="{
        aktif: false,
        judul: '',
        persen: 0,
        timer: null,
        hookTerdaftar: false,
        daftarHook() {
            if (this.hookTerdaftar || typeof Livewire === 'undefined') {
                return;
            }
            this.hookTerdaftar = true;
            Livewire.hook('request', ({ succeed, fail }) => {
                succeed(() => this.selesai());
                fail(() => this.selesai());
            });
        },
        init() {
            this.daftarHook();
        },
        mulai(judul) {
            this.daftarHook();
            this.judul = judul || 'Dokumen Akreditasi';
            this.aktif = true;
            this.persen = 12;
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                if (this.persen < 92) {
                    this.persen = Math.min(92, this.persen + Math.max(1, Math.round((94 - this.persen) * 0.12)));
                }
            }, 450);
        },
        selesai() {
            if (! this.aktif) {
                return;
            }
            clearInterval(this.timer);
            this.persen = 100;
            setTimeout(() => {
                this.aktif = false;
                this.persen = 0;
            }, 300);
        }
    }"
    x-on:akreditasi-ai-mulai.window="mulai($event.detail?.judul)"
    x-show="aktif"
    x-cloak
    x-transition
    class="mb-4 rounded-xl border border-primary-200 bg-white p-4 shadow-xs dark:border-primary-800/60 dark:bg-gray-900"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                Menyusun draf AI: <span x-text="judul"></span>
            </span>
        </div>
        <span class="text-xs font-semibold text-primary-600 dark:text-primary-400" x-text="persen + '%'"></span>
    </div>

    <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
        <div
            class="h-full rounded-full bg-primary-600 transition-all duration-300"
            :style="'width: ' + Math.max(6, persen) + '%'"
        ></div>
    </div>

    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Mohon tunggu, AI sedang menganalisis data madrasah dan menyusun narasi dokumen...
    </p>
</div>
