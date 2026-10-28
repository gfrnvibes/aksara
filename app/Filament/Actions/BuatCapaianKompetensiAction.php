<?php

namespace App\Filament\Actions;

use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Services\CapaianKompetensi\PenyusunCapaianKompetensi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Fills in the competence description of every grade row from its final score, on the report card form (one
 * student, many subjects) and on class grade entry (one subject, many students).
 */
class BuatCapaianKompetensiAction
{
    /**
     * @param  string  $pathBaris  The repeater holding the grade rows.
     * @param  bool  $mapelPerBaris  Whether each row names its own subject and KKTP (report card form) or they are
     *                               chosen once above the rows (class grade entry).
     */
    public static function make(string $pathBaris, bool $mapelPerBaris): Action
    {
        return Action::make('buatCapaian')
            ->label('Buat capaian otomatis')
            ->icon(Heroicon::Sparkles)
            ->link()
            ->visible(fn (Get $get): bool => RaporForm::skemaDari($get)->pakaiCapaianKompetensi())
            ->modalHeading('Buat capaian kompetensi')
            ->modalDescription('Capaian disusun dari Nilai Akhir dan KKTP: nilai yang sama tingkatnya mendapat deskripsi yang sama. Periksa dan sesuaikan hasilnya sebelum menyimpan.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Buat capaian')
            ->schema([
                Textarea::make('materi')
                    ->label('Materi / tujuan pembelajaran semester ini')
                    ->placeholder('Contoh: operasi bilangan bulat dan pecahan')
                    ->helperText('Opsional. Bila diisi, capaian menyebut materi ini; bila kosong, AI memilih materi yang lazim untuk kelas dan semester ini.')
                    ->rows(2)
                    ->visible(! $mapelPerBaris),
                Toggle::make('timpa')
                    ->label('Ganti capaian yang sudah terisi'),
            ])
            ->action(function (array $data, Get $get, Set $set) use ($pathBaris, $mapelPerBaris): void {
                $daftarNilai = static::nilaiYangDiisi($get, $pathBaris, $mapelPerBaris, (bool) ($data['timpa'] ?? false));

                if ($daftarNilai === []) {
                    Notification::make()
                        ->warning()
                        ->title('Tidak ada capaian yang dibuat')
                        ->body('Isi Nilai Akhir terlebih dahulu, atau aktifkan "Ganti capaian yang sudah terisi".')
                        ->send();

                    return;
                }

                $hasil = app(PenyusunCapaianKompetensi::class)->susun($daftarNilai, static::konteks($get), $data['materi'] ?? null);

                foreach ($hasil['capaian'] as $kunci => $capaian) {
                    $set("{$pathBaris}.{$kunci}.capaian_kompetensi", $capaian);
                }

                $jumlah = count($hasil['capaian']);

                Notification::make()
                    ->title($hasil['sumber'] === PenyusunCapaianKompetensi::SUMBER_GEMINI ? "Capaian {$jumlah} baris dibuat dengan Gemini" : "Capaian {$jumlah} baris dibuat dari aturan")
                    ->body($hasil['alasanGagalAi'] !== null
                        ? "Gemini tidak dapat dipakai ({$hasil['alasanGagalAi']}), jadi capaian disusun dari aturan. Periksa dan sesuaikan sebelum menyimpan."
                        : 'Periksa dan sesuaikan capaian sebelum menyimpan.')
                    ->color($hasil['alasanGagalAi'] !== null ? 'warning' : 'success')
                    ->send();
            });
    }

    /**
     * The rows that have a final score and, unless replacing, no description yet.
     *
     * @return array<array-key, array{mata_pelajaran: string, nilai: int, kktp: int}>
     */
    protected static function nilaiYangDiisi(Get $get, string $pathBaris, bool $mapelPerBaris, bool $timpa): array
    {
        $baris = array_filter((array) $get($pathBaris), is_array(...));
        $namaMapel = MataPelajaran::query()
            ->whereIn('id', $mapelPerBaris ? array_column($baris, 'mata_pelajaran_id') : [$get('mata_pelajaran_id')])
            ->pluck('nama', 'id');

        $daftarNilai = [];

        foreach ($baris as $kunci => $isi) {
            $mataPelajaran = $namaMapel[$mapelPerBaris ? ($isi['mata_pelajaran_id'] ?? null) : $get('mata_pelajaran_id')] ?? null;
            $kktp = $mapelPerBaris ? ($isi['kkm'] ?? null) : $get('kkm');

            if ($mataPelajaran === null || ! is_numeric($isi['nilai_akhir'] ?? null) || ! is_numeric($kktp) || ($isi['terkunci'] ?? false)) {
                continue;
            }

            if (filled($isi['capaian_kompetensi'] ?? null) && ! $timpa) {
                continue;
            }

            $daftarNilai[$kunci] = ['mata_pelajaran' => $mataPelajaran, 'nilai' => (int) $isi['nilai_akhir'], 'kktp' => (int) $kktp];
        }

        return $daftarNilai;
    }

    /**
     * Class level, phase and semester, which help Gemini pick fitting material. Nothing identifies the student.
     *
     * @return array{kelas: string|null, fase: string|null, semester: string|null}
     */
    protected static function konteks(Get $get): array
    {
        $tingkat = Kelas::query()->whereKey($get('kelas_id'))->value('tingkat');
        $semester = $get('semester');
        $semester = $semester instanceof Semester ? $semester : Semester::tryFrom((int) $semester);

        return [
            'kelas' => $tingkat === null ? null : (KelasResource::getTingkatOptions()[$tingkat] ?? (string) $tingkat),
            'fase' => $tingkat === null ? null : RaporForm::skemaDari($get)->fase((int) $tingkat),
            'semester' => $semester?->getLabel(),
        ];
    }
}
