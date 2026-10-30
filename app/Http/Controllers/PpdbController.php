<?php

namespace App\Http\Controllers;

use App\Models\Ppdb;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PpdbController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nisn' => ['required', 'string', 'max:30', Rule::unique('ppdbs', 'nisn')],
            'nik' => ['required', 'string', 'size:16', 'regex:/^[0-9]+$/', Rule::unique('ppdbs', 'nik')],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'alamat' => ['required', 'string', 'max:1000'],
            'nomor_telepon' => ['required', 'string', 'max:30'],
            'nomor_kip_pip' => ['nullable', 'string', 'max:100'],
            'nama_ayah_kandung' => ['required', 'string', 'max:255'],
            'nama_ibu_kandung' => ['required', 'string', 'max:255'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
        ]);

        Ppdb::create($validated);

        return back()->with('success', 'Pendaftaran PPDB berhasil dikirim. Tim kami akan segera memproses data Anda.');
    }
}
