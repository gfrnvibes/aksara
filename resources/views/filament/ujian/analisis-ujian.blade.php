<x-filament-panels::page>
    @php($warna = ['Pakai lagi' => 'success', 'Revisi' => 'warning', 'Ganti' => 'danger'])

    @if ($hasil['peserta'] === 0)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada jawaban siswa. Isi jawaban di halaman Nilai, lalu analisis muncul di sini.</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach (['Peserta dinilai' => $hasil['peserta'], 'Rata-rata' => $hasil['rerata'], 'Tertinggi' => $hasil['tertinggi'], 'Terendah' => $hasil['terendah']] as $label => $angka)
                <div class="border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ is_float($angka) ? number_format($angka, 2, ',', '.') : $angka }}</div>
                </div>
            @endforeach
        </div>

        @if ($hasil['peserta'] < 20)
            <p class="text-sm text-warning-700 dark:text-warning-400">Peserta kurang dari 20, jadi angka daya beda belum bisa diandalkan.</p>
        @endif

        <div class="overflow-x-auto border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">No</th>
                        <th class="px-3 py-2">Soal</th>
                        <th class="px-3 py-2">Kesukaran</th>
                        <th class="px-3 py-2">Daya Beda</th>
                        <th class="px-3 py-2">Pilihan dipilih <span class="font-normal">(* kunci)</span></th>
                        <th class="px-3 py-2">Rekomendasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($hasil['butir'] as $b)
                        <tr class="align-top">
                            <td class="px-3 py-2 tabular-nums">{{ $b['nomor'] }}</td>
                            <td class="max-w-md px-3 py-2">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $b['soal']->jenis->getLabel() }}</div>
                                @if ($url = $this->urlSoal($b['soal']))
                                    <a href="{{ $url }}" class="hover:underline" wire:navigate>{{ str(html_entity_decode(strip_tags($b['soal']->pertanyaan)))->squish()->limit(90) }}</a>
                                @else
                                    {{ str(html_entity_decode(strip_tags($b['soal']->pertanyaan)))->squish()->limit(90) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap"><span class="tabular-nums">{{ number_format((float) $b['kesukaran'], 2, ',', '') }}</span> · {{ \App\Services\Cbt\PenilaianUjian::labelKesukaran($b['kesukaran']) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($b['dayaBeda'] === null) - @else <span class="tabular-nums">{{ number_format($b['dayaBeda'], 2, ',', '') }}</span> · {{ \App\Services\Cbt\PenilaianUjian::labelDayaBeda($b['dayaBeda']) }} @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-x-3 gap-y-1">
                                    @forelse ($b['pengecoh'] as $o)
                                        <span @class(['tabular-nums', 'font-semibold text-success-700 dark:text-success-400' => $o['benar'], 'text-danger-600 dark:text-danger-400' => ! $o['berfungsi']]) @if (! $o['berfungsi']) title="Pengecoh dipilih kurang dari 5% siswa" @endif>{{ $o['huruf'] }}{{ $o['benar'] ? '*' : '' }} {{ round($o['persen'] * 100) }}%</span>
                                    @empty
                                        <span class="text-gray-400">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                @if (isset($warna[$b['rekomendasi']]))
                                    <x-filament::badge :color="$warna[$b['rekomendasi']]">{{ $b['rekomendasi'] }}</x-filament::badge>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Kesukaran = rata-rata skor butir ÷ bobot (di bawah 0,30 sukar, di atas 0,70 mudah). Daya beda = selisih kesukaran 27% siswa nilai teratas dan 27% terbawah (minimal 0,30 baik, di bawah 0,20 jelek). Pengecoh berfungsi bila dipilih minimal 5% siswa; yang merah perlu diganti.
        </p>
    @endif
</x-filament-panels::page>
