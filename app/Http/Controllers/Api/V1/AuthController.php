<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required_without:email', 'nullable', 'string'],
            'email' => ['required_without:login', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = trim((string) ($data['login'] ?? $data['email'] ?? ''));
        $user = User::cariDariLogin($identifier);

        if ($user === null || ! Hash::check((string) $data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identitas login atau password tidak sesuai.'],
            ]);
        }

        $gtk = $user->gtk;
        if ($gtk === null || ! $gtk->is_aktif) {
            return response()->json([
                'message' => 'Akun ini tidak terdaftar sebagai GTK aktif.',
            ], 403);
        }

        $token = $user->createToken((string) ($data['device_name'] ?? 'aksara-mobile'))->plainTextToken;

        return response()->json([
            'token' => $token,
            ...$this->profilPayload($user),
        ]);
    }

    public function loginSiswa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = trim((string) $data['nis']);
        $siswa = Siswa::query()
            ->aktif()
            ->where(fn ($q) => $q->where('nis', $identifier)->orWhere('nisn', $identifier))
            ->first();

        if ($siswa === null) {
            throw ValidationException::withMessages([
                'nis' => ['NIS / NISN siswa tidak ditemukan atau tidak aktif.'],
            ]);
        }

        $user = User::query()->where('siswa_id', $siswa->id)->first();
        if ($user === null) {
            $defaultPass = $siswa->tanggal_lahir?->format('Y-m-d');
            if ($defaultPass === null) {
                throw ValidationException::withMessages([
                    'nis' => ['Akun siswa belum memiliki tanggal lahir atau password. Hubungi admin madrasah.'],
                ]);
            }
            $user = User::query()->create([
                'siswa_id' => $siswa->id,
                'name' => $siswa->nama_lengkap,
                'email' => null,
                'password' => $defaultPass,
            ]);
        }

        if (! Hash::check((string) $data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Tanggal lahir (YYYY-MM-DD) atau password tidak sesuai.'],
            ]);
        }

        $token = $user->createToken((string) ($data['device_name'] ?? 'aksara-mobile-siswa'))->plainTextToken;

        return response()->json([
            'token' => $token,
            ...$this->profilPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($this->profilPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Berhasil keluar.',
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => (string) $data['password'],
            'wajib_ganti_password' => false,
        ]);

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    /** @return array<string, mixed> */
    protected function profilPayload(User $user): array
    {
        $user->loadMissing(['gtk', 'siswa.kelas']);
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();

        return [
            'user' => [
                'id' => $user->id,
                'role' => $user->siswa_id !== null ? 'siswa' : 'guru',
                'name' => $user->name,
                'email' => $user->email,
                'gtk' => $user->gtk === null ? null : [
                    'id' => $user->gtk->id,
                    'nama' => $user->gtk->nama,
                    'nip' => $user->gtk->nip,
                    'nuptk' => $user->gtk->nuptk,
                    'jenis_gtk' => $user->gtk->jenis_gtk->getLabel(),
                ],
                'siswa' => $user->siswa === null ? null : [
                    'id' => $user->siswa->id,
                    'nis' => $user->siswa->nis,
                    'nisn' => $user->siswa->nisn,
                    'nik' => $user->siswa->nik,
                    'nama' => $user->siswa->nama_lengkap,
                    'jenis_kelamin' => $user->siswa->jenis_kelamin?->getLabel(),
                    'tempat_lahir' => $user->siswa->tempat_lahir,
                    'tanggal_lahir' => $user->siswa->tanggal_lahir?->translatedFormat('d F Y'),
                    'agama' => $user->siswa->agama?->getLabel(),
                    'kelas' => $user->siswa->kelas?->nama,
                    'wali_kelas' => $user->siswa->kelas?->wali_kelas,
                    'status' => $user->siswa->status?->getLabel(),
                ],
            ],
            'tahun_ajaran' => $tahun === null ? null : [
                'id' => $tahun->id,
                'nama' => $tahun->nama,
                'semester' => Semester::berdasarkanTanggal(now('Asia/Jakarta'))->getLabel(),
            ],
        ];
    }
}
