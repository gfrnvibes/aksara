@php
    /** @var \App\Models\Jadwal|null $jadwal */
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\IsiJadwal>> $perHari */
    /** @var \App\Services\Jadwal\PolaJam $pola */
    /** @var \App\Enums\Hari|null $hariIni */
@endphp

<div>
    <style>
        .jadwal-saya { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .jadwal-saya-hari { border: 1px solid var(--gray-200); background: #fff; }
        .jadwal-saya-hari.hari-ini { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .jadwal-saya-hari h3 { margin: 0; padding: 0.5rem 0.75rem; font-weight: 600; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; }
        .jadwal-saya-hari.hari-ini h3 { background: var(--primary-50); color: var(--primary-700); }
        .jadwal-saya-hari ul { margin: 0; padding: 0; list-style: none; }
        .jadwal-saya-hari li { display: flex; gap: 0.75rem; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--gray-100); font-size: 0.875rem; }
        .jadwal-saya-hari li:last-child { border-bottom: 0; }
        .jadwal-saya-jam { font-family: var(--font-mono); font-size: 0.75rem; color: var(--gray-500); min-width: 6.5rem; }
        .jadwal-saya-jam b { display: block; color: var(--gray-700); }
        .jadwal-saya-libur { padding: 0.75rem; color: var(--gray-400); font-size: 0.875rem; font-style: italic; }
    </style>

    @if ($jadwal === null)
        <x-filament::empty-state
            icon="heroicon-o-calendar-days"
            heading="Jadwal belum terbit"
            description="Jadwal pelajaran semester ini belum diterbitkan oleh Wakil Kurikulum."
        />
    @else
        <div class="jadwal-saya">
            @foreach ($pola->hari() as $hari)
                @php($pelajaran = $perHari->get($hari->value, collect()))
                <section class="jadwal-saya-hari {{ $hari === $hariIni ? 'hari-ini' : '' }}">
                    <h3>
                        <span>{{ $hari->getLabel() }}</span>
                        <span>{{ $hari === $hariIni ? 'Hari ini' : $pelajaran->count().' JP' }}</span>
                    </h3>
                    @if ($pelajaran->isEmpty())
                        <div class="jadwal-saya-libur">Tidak ada jam mengajar.</div>
                    @else
                        <ul>
                            @foreach ($pelajaran as $isi)
                                @php($jam = $pola->jamUntuk($hari, $isi->jp_ke))
                                <li>
                                    <span class="jadwal-saya-jam"><b>JP {{ $isi->jp_ke }}</b>{{ $jam?->rentang() }}</span>
                                    <span><b>Kelas {{ $isi->kelas->nama }}</b><br>{{ $isi->pembagianMengajar->mataPelajaran->nama }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</div>
