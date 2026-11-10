<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Rekap Administrasi Harian</title>
<style>
@page { size: A4 landscape; margin: 12mm; } body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; } h1 { font-size: 17px; margin-bottom: 4px; } .sub { margin: 4px 0 15px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #bbb; padding: 5px; text-align: left; vertical-align: top; } th { background: #eee; } thead { display: table-header-group; } tr { page-break-inside: avoid; } .matrix th,.matrix td { padding: 3px 2px; text-align: center; font-size: 8px; } .matrix .nama { text-align: left; min-width: 100px; } .text { white-space: pre-line; overflow-wrap: anywhere; } .ttd { margin-top: 25px; text-align: right; margin-right: 35px; } .garis { margin-top: 55px; } @media print { .toolbar { display: none; } } .toolbar { margin-bottom: 12px; }
</style></head><body>

@unless($untukPdf)<div class="toolbar"><button onclick="window.print()">Cetak</button></div>
@endunless
<h1>{{ ['siswa'=>'Rekap Presensi Siswa','gtk'=>'Rekap Kehadiran GTK','jurnal'=>'Jurnal Mengajar','piket'=>'Buku Piket'][$input['jenis']] }}</h1>
<div class="sub">{{ config('sekolah.nama_singkat', config('app.name')) }} · {{ $mulai->format('d/m/Y') }} – {{ $selesai->format('d/m/Y') }}
@if($kelas) · Kelas {{ $kelas->nama }}
@endif
@if($gtk) · {{ $gtk->nama }}
@endif</div>

@if($input['jenis'] === 'siswa')
<table class="matrix"><thead><tr><th>No</th><th class="nama">Nama siswa</th>
@foreach($hari as $tanggal)<th>{{ substr($tanggal,8,2) }}</th>
@endforeach
@foreach(['H','S','I','A','T'] as $label)<th>{{ $label }}</th>
@endforeach</tr></thead><tbody>

@forelse($siswa as $anak)

@php($presensi = $records->where('siswa_id', $anak->id))
<tr><td>{{ $loop->iteration }}</td><td class="nama">{{ $anak->nama_lengkap }}</td>
@foreach($hari as $tanggal)
@php($record = $presensi->first(fn ($p) => $p->tanggal->toDateString() === $tanggal))<td>{{ $record ? ['hadir'=>'H','sakit'=>'S','izin'=>'I','alpa'=>'A'][$record->status] : '—' }}{{ $record?->terlambat_menit ? '*' : '' }}</td>
@endforeach
@foreach(['hadir','sakit','izin','alpa'] as $status)<td>{{ $presensi->where('status',$status)->count() }}</td>
@endforeach<td>{{ $presensi->where('terlambat_menit','>',0)->count() }}</td></tr>

@empty<tr><td colspan="40">Tidak ada siswa.</td></tr>
@endforelse
</tbody></table><p>H: hadir, S: sakit, I: izin, A: alpa, T / *: hadir terlambat, —: belum tercatat. Total dihitung dari data yang tersimpan.</p>

@else
<table><thead><tr><th>Tanggal</th>

@if($input['jenis']==='gtk')<th>GTK</th><th>Status</th><th>Keterangan</th><th>Pencatat</th>


@elseif($input['jenis']==='jurnal')<th>Guru / Pelaksana</th><th>Kelas / Mapel / JP</th><th>Status</th><th>Materi & Kegiatan</th><th>Refleksi & Tindak lanjut</th><th>Pencatat / Alasan</th>

@else<th>Jenis</th><th>Nama / Kelas</th><th>Kejadian</th><th>Tindak lanjut</th><th>Pencatat</th>
@endif
</tr></thead><tbody>

@forelse($records as $record)<tr><td>{{ $record->tanggal->format('d/m/Y') }}</td>

@if($input['jenis']==='gtk')<td>{{ $record->gtk?->nama }}</td><td>{{ \App\Models\PresensiGtk::STATUS[$record->status] }}</td><td class="text">{{ $record->catatan }}</td><td>{{ $record->pencatat?->name }}</td>


@elseif($input['jenis']==='jurnal')<td>{{ $record->nama_guru }}
@if($record->pelaksana_id && $record->pelaksana_id !== $record->guru_id)<br>Pengganti: {{ $record->pelaksana?->nama }}
@endif</td><td>{{ $record->nama_kelas }}<br>{{ $record->nama_mapel }}<br>JP {{ $record->jp_mulai }}–{{ $record->jp_selesai }}</td><td>{{ \App\Models\JurnalMengajar::STATUS[$record->status] }}</td><td class="text">{{ $record->materi }}<br>{{ $record->kegiatan }}</td><td class="text">{{ $record->refleksi }}<br>{{ $record->tindak_lanjut }}</td><td class="text">{{ $record->pencatat?->name }}<br>{{ $record->alasan }}</td>

@else<td>{{ \App\Models\CatatanPiket::JENIS[$record->jenis] }}</td><td>{{ $record->siswa?->nama_lengkap ?? $record->gtk?->nama }}<br>{{ $record->kelas?->nama }} {{ $record->jam_pelajaran }}
@if($record->terlambat_menit)<br>{{ $record->terlambat_menit }} menit
@endif</td><td class="text">{{ $record->kejadian }}</td><td class="text">{{ $record->tindak_lanjut }}</td><td>{{ $record->pencatat?->name }}</td>
@endif
</tr>
@empty<tr><td colspan="8">Belum ada data tercatat dalam periode ini.</td></tr>
@endforelse
</tbody></table>

@endif
@if($input['jenis'] === 'gtk')
    <h2>Total kehadiran dalam periode</h2>
    <table>
        <thead><tr><th>GTK</th><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Dinas luar</th><th>Alpa</th></tr></thead>
        <tbody>
        @forelse($records->groupBy('gtk_id') as $presensi)
            <tr>
                <td>{{ $presensi->first()->gtk?->nama }}</td>
                @foreach(['hadir', 'sakit', 'izin', 'dinas_luar', 'alpa'] as $status)
                    <td>{{ $presensi->where('status', $status)->count() }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="6">Belum ada data kehadiran.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p>Total berdasarkan tanggal yang diisi. Hari yang tidak tercatat tidak dihitung sebagai alpa.</p>
@endif
<div class="ttd">Mengetahui / memeriksa,<div class="garis">{{ $kelas?->wali_kelas ?? $gtk?->nama ?? '(........................................)' }}</div><div>Nama dan tanda tangan</div></div>
</body></html>
