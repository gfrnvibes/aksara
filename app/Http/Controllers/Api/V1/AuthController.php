<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
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

    /** @return array<string, mixed> */
    protected function profilPayload(User $user): array
    {
        $user->loadMissing('gtk');
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'gtk' => $user->gtk === null ? null : [
                    'id' => $user->gtk->id,
                    'nama' => $user->gtk->nama,
                    'nip' => $user->gtk->nip,
                    'nuptk' => $user->gtk->nuptk,
                    'jenis_gtk' => $user->gtk->jenis_gtk->getLabel(),
                ],
            ],
            'tahun_ajaran' => $tahun === null ? null : [
                'id' => $tahun->id,
                'nama' => $tahun->nama,
                'semester' => Semester::berdasarkanTanggal(now())->getLabel(),
            ],
        ];
    }
}
