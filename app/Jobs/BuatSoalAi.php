<?php

namespace App\Jobs;

use App\Enums\JenisSoal;
use App\Enums\Kurikulum;
use App\Enums\LevelKognitif;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\MataPelajaran;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Services\Cbt\PenyusunSoalAi;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drafts the questions of one blueprint row with Gemini. Each row is its own request, so a long request list
 * neither hits Gemini's output limit nor loses every row when one fails; the queue sends them one after another.
 */
class BuatSoalAi implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    /**
     * @param  array{materi: string, indikator?: string|null, jenis: string, level_kognitif?: string|null, jumlah: int}  $baris
     */
    public function __construct(public int $userId, public int $mataPelajaranId, public int $tingkat, public array $baris) {}

    public static function kunciJumlah(string $batchId): string
    {
        return "soal-ai:{$batchId}:jumlah";
    }

    public static function kunciGagal(string $batchId): string
    {
        return "soal-ai:{$batchId}:gagal";
    }

    public function handle(PenyusunSoalAi $penyusun): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $kurikulum = TahunAjaran::query()->where('is_aktif', true)->value('kurikulum');
        $level = LevelKognitif::tryFrom((string) ($this->baris['level_kognitif'] ?? ''));

        try {
            $daftar = $penyusun->susun([
                'mata_pelajaran' => (string) MataPelajaran::query()->whereKey($this->mataPelajaranId)->value('nama'),
                'kelas' => (string) (KelasResource::getTingkatOptions()[$this->tingkat] ?? $this->tingkat),
                'kurikulum' => ($kurikulum instanceof Kurikulum ? $kurikulum : Kurikulum::bawaan())->getLabel(),
                'jenis' => JenisSoal::from($this->baris['jenis']),
                'jumlah' => (int) $this->baris['jumlah'],
                'materi' => $this->baris['materi'],
                'indikator' => $this->baris['indikator'] ?? null,
                'level_kognitif' => $level,
            ]);
        } catch (Throwable $exception) {
            // Busy or out of quota: wait and try again; anything else will not get better by retrying.
            if (static::sementara($exception) && $this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());

                return;
            }

            // Before fail(): failing the last job runs the batch's finally callback, which reads this list.
            $this->catatGagal();
            $this->fail($exception);

            return;
        }

        DB::transaction(function () use ($daftar): void {
            foreach ($daftar as $soal) {
                Soal::query()->create([
                    ...$soal,
                    'mata_pelajaran_id' => $this->mataPelajaranId,
                    'tingkat' => $this->tingkat,
                    'user_id' => $this->userId,
                    'is_siap' => false,
                ]);
            }
        });

        if ($this->batch() !== null) {
            // Some cache stores (database) only increment a key that exists.
            Cache::add(static::kunciJumlah($this->batch()->id), 0, now()->addDay());
            Cache::increment(static::kunciJumlah($this->batch()->id), count($daftar));
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    protected function catatGagal(): void
    {
        if ($this->batch() !== null) {
            // shortcut: read-modify-write without a lock, fine with one queue worker; add Cache::lock if workers multiply.
            $kunci = static::kunciGagal($this->batch()->id);
            Cache::put($kunci, [...Cache::get($kunci, []), $this->baris['materi']], now()->addDay());
        }
    }

    protected static function sementara(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && in_array($exception->response->status(), [429, 500, 503, 504], true));
    }
}
