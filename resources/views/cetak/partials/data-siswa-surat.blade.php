@php
    /** @var \App\Models\Siswa $siswa */
    /** @var string|null $kelas */
    $orangTua = $siswa->ayah?->nama ?? $siswa->ibu?->nama ?? $siswa->wali?->nama;
@endphp
<table class="data">
    <tr><td class="label">Nama</td><td class="sep">:</td><td><strong>{{ $siswa->nama_lengkap }}</strong></td></tr>
    <tr><td class="label">NIS / NISN</td><td class="sep">:</td><td>{{ $siswa->nis }} / {{ $siswa->nisn ?: '-' }}</td></tr>
    <tr><td class="label">Tempat, Tanggal Lahir</td><td class="sep">:</td><td>{{ $siswa->tempat_lahir ?: '-' }}, {{ $siswa->tanggal_lahir?->translatedFormat('d F Y') ?? '-' }}</td></tr>
    <tr><td class="label">Jenis Kelamin</td><td class="sep">:</td><td>{{ $siswa->jenis_kelamin->getLabel() }}</td></tr>
    <tr><td class="label">Kelas</td><td class="sep">:</td><td>{{ $kelas ?: '-' }}</td></tr>
    <tr><td class="label">Nama Orang Tua / Wali</td><td class="sep">:</td><td>{{ $orangTua ?: '-' }}</td></tr>
    <tr><td class="label">Alamat</td><td class="sep">:</td><td>{{ $siswa->alamat ?: '-' }}</td></tr>
</table>
