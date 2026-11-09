<?php

namespace App\Services\Surat;

use App\Models\KopSurat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PengaturanKop
{
    public const array JENIS = ['surat_keluar' => 'Surat keluar', 'surat_keterangan' => 'Surat keterangan, pindah & transkrip', 'rapor' => 'Rapor'];

    /** @return array<string, mixed> */
    public static function bawaan(): array
    {
        return [
            'mode' => 'terstruktur', 'instansi' => 'KEMENTERIAN AGAMA REPUBLIK INDONESIA', 'nama' => (string) config('sekolah.nama'),
            'alamat' => config('sekolah.alamat'), 'wilayah' => config('sekolah.wilayah'), 'telepon' => config('sekolah.telepon'),
            'email' => config('sekolah.email'), 'website' => null, 'npsn' => config('sekolah.npsn'), 'nsm' => null,
            'logo_kiri' => null, 'logo_kanan' => null, 'gambar' => null, 'tampilkan_logo_kiri' => true, 'tampilkan_logo_kanan' => true,
            'logo_kiri_mm' => 22, 'logo_kanan_mm' => 22, 'tinggi_gambar_mm' => 35,
            'ukuran_huruf' => 10, 'ukuran_nama' => 16, 'perataan' => 'center', 'jarak_baris' => 1.3, 'garis' => 'double', 'jarak_bawah_mm' => 4,
        ];
    }

    /** @return array<string, mixed> */
    public static function untuk(string $jenis): array
    {
        $json = DB::table('default_kop_surats')->where('jenis', $jenis)->value('konfigurasi');

        return is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : [...static::bawaan(), 'legacy' => true];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function validasi(array $data): array
    {
        $rules = [
            'mode' => ['required', Rule::in(['terstruktur', 'gambar'])], 'nama' => ['required', 'string', 'max:255'],
            'perataan' => ['required', Rule::in(['left', 'center', 'right'])], 'garis' => ['required', Rule::in(['none', 'solid', 'double'])],
            'ukuran_huruf' => ['required', 'numeric', 'between:8,16'], 'ukuran_nama' => ['required', 'numeric', 'between:12,28'],
            'jarak_baris' => ['required', 'numeric', 'between:1,2'], 'jarak_bawah_mm' => ['required', 'numeric', 'between:2,12'],
            'logo_kiri_mm' => ['required', 'numeric', 'between:12,35'], 'logo_kanan_mm' => ['required', 'numeric', 'between:12,35'],
            'tinggi_gambar_mm' => ['required', 'numeric', 'between:15,60'],
            'tampilkan_logo_kiri' => ['required', 'boolean'], 'tampilkan_logo_kanan' => ['required', 'boolean'],
        ];
        foreach (['instansi', 'alamat', 'wilayah', 'telepon', 'email', 'website', 'npsn', 'nsm'] as $key) {
            $rules[$key] = ['nullable', 'string', 'max:500'];
        }
        foreach (['logo_kiri', 'logo_kanan', 'gambar'] as $key) {
            $rules[$key] = ['nullable', 'string', 'max:255', 'regex:~^kop-surat/[a-zA-Z0-9_./-]+$~'];
        }
        $valid = Validator::make([...static::bawaan(), ...$data], $rules)->validate();
        foreach (['logo_kiri', 'logo_kanan', 'gambar'] as $key) {
            if (filled($valid[$key] ?? null) && (str_contains($valid[$key], '..') || static::gambar($valid[$key]) === null)) {
                throw ValidationException::withMessages(["konfigurasi.{$key}" => 'Gambar kop harus berupa PNG/JPG yang tersimpan dan dapat dibaca.']);
            }
        }
        if ($valid['mode'] === 'gambar' && blank($valid['gambar'])) {
            throw ValidationException::withMessages(['konfigurasi.gambar' => 'Unggah gambar kop terlebih dahulu.']);
        }

        return $valid;
    }

    /** @param list<string> $jenis */
    public static function terapkan(KopSurat $kop, array $jenis): void
    {
        Validator::make(['jenis' => $jenis], ['jenis' => ['required', 'array', 'min:1'], 'jenis.*' => ['required', Rule::in(array_keys(static::JENIS)), 'distinct']])->validate();
        DB::transaction(function () use ($kop, $jenis): void {
            $draft = KopSurat::query()->whereKey($kop->id)->lockForUpdate()->firstOrFail();
            $config = [...static::validasi($draft->konfigurasi), 'sumber_id' => $draft->id, 'sumber_nama' => $draft->nama, 'versi' => (string) Str::uuid()];
            foreach ($jenis as $item) {
                DB::table('default_kop_surats')->updateOrInsert(['jenis' => $item], ['kop_surat_id' => $draft->id, 'konfigurasi' => json_encode($config, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            }
            activity()->performedOn($draft)->event('kop_diterapkan')->withProperties(['jenis' => $jenis, 'versi' => $config['versi']])->log('Kop surat dijadikan default');
        });
    }

    public static function gambar(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = array_values($path)[0] ?? null;
        }
        if ($path instanceof TemporaryUploadedFile) {
            $bytes = $path->get();
        } elseif (is_string($path) && str_starts_with($path, 'kop-surat/') && ! str_contains($path, '..') && Storage::disk('public')->exists($path)) {
            $bytes = Storage::disk('public')->get($path);
        } else {
            return null;
        }
        if (! is_string($bytes) || strlen($bytes) > 2 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
    }
}
