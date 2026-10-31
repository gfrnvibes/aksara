<?php

namespace App\Http\Requests;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Filament\Resources\Ppdbs\Schemas\PpdbForm;
use App\Models\Ppdb;
use App\Models\TahunAjaran;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A registration sent from the public PPDB form, for the academic year whose registration is open today.
 */
class DaftarPpdbRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TahunAjaran::pendaftaranPpdbDibuka() !== null;
    }

    /**
     * A closed registration is reported on the form instead of as a forbidden page.
     */
    protected function failedAuthorization(): void
    {
        throw ValidationException::withMessages(['ppdb' => 'Pendaftaran SPMB sedang ditutup.']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tahunAjaranId = TahunAjaran::pendaftaranPpdbDibuka()?->id;
        $dokumen = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.PpdbForm::UKURAN_MAKS_KB];

        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nisn' => ['required', 'digits:10', Rule::unique(Ppdb::class)->where('tahun_ajaran_id', $tahunAjaranId)],
            'nik' => ['required', 'digits:16', Rule::unique(Ppdb::class)->where('tahun_ajaran_id', $tahunAjaranId)],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
            'agama' => ['required', Rule::enum(Agama::class)],
            'asal_sekolah' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:1000'],
            'nomor_telepon' => ['required', 'regex:/^\+?[0-9][0-9\s-]{7,19}$/'],
            'nomor_kip_pip' => ['nullable', 'string', 'max:100'],
            'nama_ayah_kandung' => ['required', 'string', 'max:255'],
            'nama_ibu_kandung' => ['required', 'string', 'max:255'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'berkas.kk' => ['required', ...$dokumen],
            'berkas.akta' => ['required', ...$dokumen],
            'berkas.ijazah' => ['nullable', ...$dokumen],
            'berkas.foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.PpdbForm::UKURAN_MAKS_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama_lengkap' => 'nama lengkap',
            'nisn' => 'NISN',
            'nik' => 'NIK',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'jenis_kelamin' => 'jenis kelamin',
            'asal_sekolah' => 'asal sekolah',
            'nomor_telepon' => 'nomor HP',
            'nomor_kip_pip' => 'nomor KIP/PIP',
            'nama_ayah_kandung' => 'nama ayah kandung',
            'nama_ibu_kandung' => 'nama ibu kandung',
            'nama_wali' => 'nama wali',
            ...collect(Ppdb::BERKAS)->mapWithKeys(fn (string $judul, string $kunci): array => ["berkas.{$kunci}" => $judul])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nisn.unique' => 'NISN ini sudah terdaftar pada SPMB tahun ini. Cek status pendaftaran dengan nomor pendaftaran Anda.',
            'nik.unique' => 'NIK ini sudah terdaftar pada SPMB tahun ini. Cek status pendaftaran dengan nomor pendaftaran Anda.',
            'nomor_telepon.regex' => 'Nomor HP hanya boleh berisi angka, misalnya 081234567890.',
        ];
    }
}
