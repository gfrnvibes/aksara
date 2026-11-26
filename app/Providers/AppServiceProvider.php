<?php

namespace App\Providers;

use App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages\EditAkreditasiDokumen;
use App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages\ListAkreditasiDokumens;
use App\Models\PelanggaranSiswa;
use App\Models\Pengumuman;
use App\Models\PresensiSiswa;
use App\Models\PrestasiSiswa;
use App\Models\Rapor;
use App\Models\TabunganSiswa;
use App\Models\TagihanSiswa;
use App\Models\TransaksiKeuangan;
use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use App\Services\Notifikasi\NotifikasiAktivitasSiswa;
use App\Support\WindowsPostgreSqlDumper;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\View\PanelsRenderHook;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.components.sidebar-resize'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_START,
            fn () => view('filament.akreditasi.partials.ai-loading-banner'),
            scopes: [ListAkreditasiDokumens::class, EditAkreditasiDokumen::class],
        );

        TextColumn::configureUsing(function (TextColumn $column): void {
            $name = $column->getName();
            if (
                str_ends_with($name, '_count')
                || in_array($name, [
                    'nis', 'nik', 'nisn', 'nip', 'nuptk', 'nomor_hp', 'no_hp',
                    'nomor_peserta', 'nomor_ijazah', 'no_kk', 'nomor', 'kode',
                    'tahun', 'nilai_terisi', 'kehadiran', 'periode',
                ], true)
            ) {
                $column->fontFamily('mono');
            }
        });

        $this->registerGoogleDriveDisk();
        $this->registerPostgreSqlDumper();
        $this->registerNotifikasiSiswaListeners();
    }

    /**
     * Preserve Windows system environment variables so PostgreSQL 18 pg_dump can generate its restrict key.
     */
    protected function registerPostgreSqlDumper(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        DbDumperFactory::extend('pgsql', fn (): PostgreSql => new WindowsPostgreSqlDumper);
    }

    /**
     * Register the "google" filesystem driver used as the off-site backup destination.
     */
    protected function registerGoogleDriveDisk(): void
    {
        Storage::extend('google', function (Application $app, array $config): FilesystemAdapter {
            $client = new GoogleClient;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $adapter = new GoogleDriveAdapter(new Drive($client), $config['folder'] ?? '/');

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(6));
    }

    protected function registerNotifikasiSiswaListeners(): void
    {
        PresensiSiswa::saved(function (PresensiSiswa $presensi): void {
            if (! $presensi->wasRecentlyCreated && ! $presensi->wasChanged('status')) {
                return;
            }
            $label = PresensiSiswa::STATUS[$presensi->status] ?? ucfirst($presensi->status);
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $presensi->siswa_id,
                'presensi',
                'Presensi Harian',
                "Kehadiran tanggal {$presensi->tanggal->format('d-m-Y')}: {$label}.",
                ['tanggal' => $presensi->tanggal->format('Y-m-d'), 'status' => $presensi->status],
            );
        });

        PelanggaranSiswa::created(function (PelanggaranSiswa $pelanggaran): void {
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $pelanggaran->siswa_id,
                'pelanggaran',
                'Catatan Pelanggaran',
                "{$pelanggaran->pelanggaran} (+{$pelanggaran->poin} poin).",
                ['id' => $pelanggaran->id],
            );
        });

        PrestasiSiswa::created(function (PrestasiSiswa $prestasi): void {
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $prestasi->siswa_id,
                'prestasi',
                'Catatan Prestasi',
                "{$prestasi->nama_prestasi}".($prestasi->peringkat ? " ({$prestasi->peringkat})" : '').'.',
                ['id' => $prestasi->id],
            );
        });

        TabunganSiswa::created(function (TabunganSiswa $tabungan): void {
            $jenis = $tabungan->jenis === 'setor' ? 'Setoran Tabungan' : 'Penarikan Tabungan';
            $nominal = number_format($tabungan->nominal, 0, ',', '.');
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $tabungan->siswa_id,
                'tabungan',
                $jenis,
                "{$jenis} sebesar Rp {$nominal}.",
                ['id' => $tabungan->id, 'jenis' => $tabungan->jenis],
            );
        });

        TagihanSiswa::created(function (TagihanSiswa $tagihan): void {
            $nominal = number_format($tagihan->nominal, 0, ',', '.');
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $tagihan->siswa_id,
                'tagihan',
                'Tagihan Sekolah Baru',
                "{$tagihan->nama} ({$tagihan->periode}) sebesar Rp {$nominal}.",
                ['id' => $tagihan->id],
            );
        });

        TransaksiKeuangan::created(function (TransaksiKeuangan $transaksi): void {
            if ($transaksi->tagihan_siswa_id === null) {
                return;
            }
            $tagihan = $transaksi->tagihanSiswa;
            if ($tagihan === null) {
                return;
            }
            $nominal = number_format($transaksi->nominal, 0, ',', '.');
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $tagihan->siswa_id,
                'tagihan',
                'Pembayaran Tagihan',
                "Pembayaran {$tagihan->nama} sebesar Rp {$nominal} telah dicatat.",
                ['tagihan_id' => $tagihan->id, 'transaksi_id' => $transaksi->id],
            );
        });

        Rapor::saved(function (Rapor $rapor): void {
            if (! $rapor->wasChanged(['catatan', 'keputusan'])) {
                return;
            }
            if (blank($rapor->catatan) && $rapor->keputusan === null) {
                return;
            }
            app(NotifikasiAktivitasSiswa::class)->kirimKeSiswa(
                $rapor->siswa_id,
                'nilai',
                'Pembaruan Rapor',
                'Catatan wali kelas atau status rapor semester telah diperbarui.',
                ['rapor_id' => $rapor->id],
            );
        });

        Pengumuman::created(function (Pengumuman $pengumuman): void {
            app(NotifikasiAktivitasSiswa::class)->kirimPengumuman($pengumuman);
        });
    }
}
