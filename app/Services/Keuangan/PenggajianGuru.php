<?php

namespace App\Services\Keuangan;

use App\Enums\JenisGtk;
use App\Models\GajiGuru;
use App\Models\Gtk;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PenggajianGuru
{
    /** @param array<string, mixed> $data */
    public function simpan(array $data, ?int $id = null): GajiGuru
    {
        $user = PencatatanKeuangan::izinkan();
        $data = Validator::make($data, [
            'gtk_id' => ['required', 'integer', Rule::exists('gtks', 'id')->where('jenis_gtk', JenisGtk::Guru->value)],
            'periode' => ['required', 'date_format:Y-m'],
            'gaji_pokok' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'tunjangan' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'potongan' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        if ((int) $data['gaji_pokok'] + (int) $data['tunjangan'] <= (int) $data['potongan']) {
            throw ValidationException::withMessages(['potongan' => 'Gaji bersih harus lebih besar dari nol.']);
        }

        return DB::transaction(function () use ($data, $id, $user): GajiGuru {
            $gaji = $id === null ? new GajiGuru : GajiGuru::query()->lockForUpdate()->findOrFail($id);
            if ($gaji->exists && $gaji->pembayaran()->exists()) {
                throw ValidationException::withMessages(['gaji_pokok' => 'Gaji sudah dibayar. Batalkan transaksi pembayaran sebelum melakukan koreksi.']);
            }
            // A payroll's teacher and month remain fixed for its entire audit trail.
            if ($gaji->exists && ($gaji->gtk_id !== (int) $data['gtk_id'] || $gaji->periode !== $data['periode'])) {
                throw ValidationException::withMessages(['gtk_id' => 'Guru dan periode gaji tidak dapat diganti.']);
            }
            $guru = Gtk::query()->lockForUpdate()->findOrFail((int) $data['gtk_id']);
            if (! $gaji->exists && ! $guru->is_aktif) {
                throw ValidationException::withMessages(['gtk_id' => 'Pilih guru yang masih aktif.']);
            }
            Validator::make($data, ['periode' => [Rule::unique('gaji_gurus')->where('gtk_id', $guru->id)->ignore($gaji->getKey())]])->validate();
            $gaji->fill($data);
            if (! $gaji->exists) {
                $gaji->fill(['nama_guru' => $guru->nama, 'nip' => $guru->nip, 'pencatat_id' => $user->id]);
            }
            $gaji->save();

            return $gaji;
        });
    }

    /** @param array<string, mixed> $data */
    public function bayar(int $id, array $data): TransaksiKeuangan
    {
        PencatatanKeuangan::izinkan();

        return DB::transaction(function () use ($id, $data): TransaksiKeuangan {
            $gaji = GajiGuru::query()->lockForUpdate()->findOrFail($id);
            if ($gaji->pembayaran()->exists()) {
                throw ValidationException::withMessages(['tanggal' => 'Gaji periode ini sudah dibayar.']);
            }
            $transaksi = app(PencatatanKeuangan::class)->catat([
                'tanggal' => $data['tanggal'] ?? null, 'metode' => $data['metode'] ?? null, 'referensi' => $data['referensi'] ?? null,
                'jenis' => 'pengeluaran', 'kategori' => 'Gaji Guru',
                'uraian' => Str::limit("Gaji {$gaji->periode} - {$gaji->nama_guru}", 255, ''), 'nominal' => $gaji->bersih(),
            ]);
            $transaksi->update(['gaji_guru_id' => $gaji->id]);

            return $transaksi;
        });
    }
}
