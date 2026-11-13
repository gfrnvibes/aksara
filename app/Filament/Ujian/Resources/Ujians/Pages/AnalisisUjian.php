<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Soal;
use App\Models\Ujian;
use App\Services\Cbt\PenilaianUjian;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * How each question of an exam performed: difficulty, discrimination and, for PG, which distractors worked.
 */
class AnalisisUjian extends Page
{
    use InteractsWithRecord;

    protected static string $resource = UjianResource::class;

    protected string $view = 'filament.ujian.analisis-ujian';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(UjianResource::canView($this->record), 403);
    }

    public function ujian(): Ujian
    {
        /** @var Ujian */
        return $this->getRecord();
    }

    public function getTitle(): string
    {
        return "Analisis Butir · {$this->ujian()->nama}";
    }

    public function getBreadcrumbs(): array
    {
        return [UjianResource::getUrl() => 'Ujian', UjianResource::getUrl('nilai', ['record' => $this->ujian()]) => $this->ujian()->nama, 'Analisis Butir'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['hasil' => PenilaianUjian::analisis($this->ujian())];
    }

    /**
     * Where to revise the question, if this user may.
     */
    public function urlSoal(Soal $soal): ?string
    {
        return SoalResource::canEdit($soal) ? SoalResource::getUrl('edit', ['record' => $soal]) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nilai')
                ->label('Nilai')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->url(fn (): string => UjianResource::getUrl('nilai', ['record' => $this->ujian()])),
            Action::make('unduh')
                ->label('Unduh Analisis (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->unduh()),
        ];
    }

    protected function unduh(): StreamedResponse
    {
        $ujian = $this->ujian();
        $hasil = PenilaianUjian::analisis($ujian);
        $angka = fn (?float $x): string => $x === null ? '' : str_replace('.', ',', (string) $x);

        return response()->streamDownload(function () use ($hasil, $angka): void {
            $keluar = new SplFileObject('php://output', 'w');
            $keluar->fwrite("\u{FEFF}"); // Excel needs the BOM to read UTF-8 text.
            $keluar->fputcsv(['No', 'Jenis', 'Soal', 'Kesukaran', 'Kategori', 'Daya Beda', 'Kategori', 'Pengecoh', 'Rekomendasi'], ';');

            foreach ($hasil['butir'] as $b) {
                $keluar->fputcsv([
                    $b['nomor'],
                    $b['soal']->jenis->getLabel(),
                    str(html_entity_decode(strip_tags($b['soal']->pertanyaan)))->squish()->limit(150),
                    $angka($b['kesukaran']),
                    PenilaianUjian::labelKesukaran($b['kesukaran']),
                    $angka($b['dayaBeda']),
                    PenilaianUjian::labelDayaBeda($b['dayaBeda']),
                    implode(' ', array_map(fn (array $o): string => $o['huruf'].($o['benar'] ? '*' : '').' '.round($o['persen'] * 100).'%', $b['pengecoh'])),
                    $b['rekomendasi'],
                ], ';');
            }
        }, str($ujian->nama.' '.$ujian->mataPelajaran->nama)->slug().'-analisis.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
