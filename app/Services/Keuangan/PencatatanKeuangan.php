<?php

namespace App\Services\Keuangan;

use App\Models\GajiGuru;
use App\Models\TagihanSiswa;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PencatatanKeuangan
{
    public static function izinkan(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->bolehKelolaKeuangan(), 403);

        return $user;
    }

    /** @param array<string, mixed> $data */
    public function catat(array $data, ?int $tagihanId = null): TransaksiKeuangan
    {
        $user = self::izinkan();
        $data = Validator::make($data, [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'metode' => ['required', 'in:tunai,transfer'],
            'referensi' => ['nullable', 'string', 'max:255'],
            'jenis' => [$tagihanId === null ? 'required' : 'nullable', 'in:pemasukan,pengeluaran'],
            'kategori' => [$tagihanId === null ? 'required' : 'nullable', 'string', 'max:255'],
            'uraian' => [$tagihanId === null ? 'required' : 'nullable', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($data, $tagihanId, $user): TransaksiKeuangan {
            if ($tagihanId !== null) {
                $tagihan = TagihanSiswa::query()->lockForUpdate()->findOrFail($tagihanId);
                if ($tagihan->dibatalkan_at !== null || (int) $data['nominal'] > $tagihan->sisa()) {
                    throw ValidationException::withMessages(['nominal' => 'Pembayaran melebihi sisa tagihan atau tagihan sudah dibatalkan.']);
                }
                $data = [...$data, 'jenis' => 'pemasukan', 'kategori' => $tagihan->nama, 'uraian' => Str::limit("{$tagihan->nama} - {$tagihan->periode} - {$tagihan->siswa->nama_lengkap}", 255, '')];
            }

            return TransaksiKeuangan::query()->create([
                ...$data, 'nomor' => (string) Str::uuid(), 'tagihan_siswa_id' => $tagihanId, 'pencatat_id' => $user->id,
            ]);
        });
    }

    public function batalkanTransaksi(int $id, string $alasan): void
    {
        $user = self::izinkan();
        $this->validasiAlasan($alasan);
        DB::transaction(function () use ($id, $alasan, $user): void {
            // Lock the invoice first, as payments do, to serialize its balance changes.
            $awal = TransaksiKeuangan::query()->findOrFail($id);
            if ($awal->tagihan_siswa_id !== null) {
                TagihanSiswa::query()->lockForUpdate()->findOrFail($awal->tagihan_siswa_id);
            }
            if ($awal->gaji_guru_id !== null) {
                GajiGuru::query()->lockForUpdate()->findOrFail($awal->gaji_guru_id);
            }
            $transaksi = TransaksiKeuangan::query()->lockForUpdate()->findOrFail($id);
            if ($transaksi->dibatalkan_at !== null) {
                throw ValidationException::withMessages(['alasan' => 'Transaksi sudah dibatalkan.']);
            }
            $transaksi->update(['dibatalkan_at' => now(), 'pembatal_id' => $user->id, 'alasan_pembatalan' => trim($alasan)]);
        });
    }

    public function batalkanTagihan(int $id, string $alasan): void
    {
        $user = self::izinkan();
        $this->validasiAlasan($alasan);
        DB::transaction(function () use ($id, $alasan, $user): void {
            $tagihan = TagihanSiswa::query()->lockForUpdate()->findOrFail($id);
            if ($tagihan->dibatalkan_at !== null || $tagihan->terbayar() > 0) {
                throw ValidationException::withMessages(['alasan' => 'Batalkan pembayaran aktif terlebih dahulu. Tagihan yang sudah dibatalkan tidak dapat dibatalkan lagi.']);
            }
            $tagihan->update(['dibatalkan_at' => now(), 'pembatal_id' => $user->id, 'alasan_pembatalan' => trim($alasan)]);
        });
    }

    private function validasiAlasan(string $alasan): void
    {
        Validator::make(['alasan' => trim($alasan)], ['alasan' => ['required', 'string', 'min:5', 'max:1000']])->validate();
    }
}
