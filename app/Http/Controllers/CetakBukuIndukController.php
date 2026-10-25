<?php

namespace App\Http\Controllers;

use App\Enums\IsiDokumen;
use App\Exports\BukuIndukExport;
use App\Models\Rapor;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CetakBukuIndukController extends Controller
{
    /**
     * Minutes a mass-print request stays available after it is prepared.
     */
    public const int MASA_BERLAKU_MENIT = 30;

    /**
     * Print or download the Buku Induk of one student.
     */
    public function siswa(Request $request, Siswa $siswa): View|Response
    {
        Gate::authorize('view', $siswa);

        $isi = IsiDokumen::tryFrom((string) $request->query('isi')) ?? IsiDokumen::Keduanya;

        return $this->tampilkan(Siswa::query()->whereKey($siswa->getKey()), $isi, $request->query('format') === 'pdf', $siswa->nis);
    }

    /**
     * Print or download the Buku Induk of every student in a request prepared by {@see self::siapkanCetakMassal()}.
     */
    public function massal(Request $request, string $token): View|Response
    {
        /** @var array{user_id: int, siswa_ids: array<int, int>, isi: string}|null $permintaan */
        $permintaan = Cache::get(static::kunciCache($token));

        abort_if($permintaan === null || $permintaan['user_id'] !== $request->user()->getKey(), 404);

        Gate::authorize('viewAny', Siswa::class);

        $isi = IsiDokumen::from($permintaan['isi']);

        return $this->tampilkan(Siswa::query()->whereKey($permintaan['siswa_ids']), $isi, $request->query('format') === 'pdf', 'massal');
    }

    /**
     * Remember which students the current user wants to print and return the token for {@see self::massal()}.
     *
     * @param  array<int, int>  $siswaIds
     */
    public static function siapkanCetakMassal(array $siswaIds, IsiDokumen $isi): string
    {
        $token = (string) Str::uuid();

        Cache::put(static::kunciCache($token), [
            'user_id' => auth()->id(),
            'siswa_ids' => array_values($siswaIds),
            'isi' => $isi->value,
        ], now()->addMinutes(static::MASA_BERLAKU_MENIT));

        return $token;
    }

    /**
     * @param  Builder<Siswa>  $query
     */
    protected function tampilkan(Builder $query, IsiDokumen $isi, bool $sebagaiPdf, string $namaFile): View|Response
    {
        if ($isi->termasukNilai()) {
            Gate::authorize('viewAny', Rapor::class);
        }

        $daftarSiswa = BukuIndukExport::muatSiswa($query);

        if ($sebagaiPdf) {
            return response(BukuIndukExport::pdfBukuInduk($daftarSiswa, $isi), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.BukuIndukExport::namaFile("buku-induk {$namaFile}", $isi, 'pdf').'"',
            ]);
        }

        return view('cetak.buku-induk', BukuIndukExport::dataBukuInduk($daftarSiswa, $isi));
    }

    protected static function kunciCache(string $token): string
    {
        return "cetak-buku-induk:{$token}";
    }
}
