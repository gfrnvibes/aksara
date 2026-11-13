<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Enums\JenisSoal;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\PesertaUjian;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Services\Cbt\NaskahUjian;
use App\Services\Cbt\PenilaianUjian;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The students of an exam with their packet and score. Answers are typed in from the paper answer sheets.
 */
class NilaiUjian extends ManageRelatedRecords
{
    protected static string $resource = UjianResource::class;

    protected static string $relationship = 'peserta';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Ujian && UjianResource::canView($record);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->bolehMengisi()) {
            PenilaianUjian::muatPeserta($this->ujian());
        }
    }

    public function ujian(): Ujian
    {
        /** @var Ujian */
        return $this->getOwnerRecord();
    }

    public function bolehMengisi(): bool
    {
        return UjianResource::canEdit($this->ujian());
    }

    public function getTitle(): string
    {
        return "Nilai · {$this->ujian()->nama}";
    }

    public function getBreadcrumbs(): array
    {
        return [UjianResource::getUrl() => 'Ujian', $this->ujian()->nama, 'Nilai'];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['siswa', 'kelas']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy('kelas_id')
                ->orderBy(Siswa::withTrashed()->select('nama_lengkap')->whereColumn('siswas.id', 'peserta_ujians.siswa_id')))
            ->paginated([50, 'all'])
            ->columns([
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama')
                    ->description(fn (PesertaUjian $record): string => $record->siswa->nis)
                    ->searchable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                SelectColumn::make('paket')
                    ->options(fn (): array => array_combine($this->ujian()->daftarPaket(), $this->ujian()->daftarPaket()))
                    ->selectablePlaceholder(false)
                    ->disabled(fn (): bool => ! $this->bolehMengisi())
                    ->afterStateUpdated(fn (PesertaUjian $record) => PenilaianUjian::nilaiUlang($this->ujian(), $record)),
                TextColumn::make('nilai')
                    ->numeric(2)
                    ->placeholder('Belum diisi')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kelas_id')->label('Kelas')->relationship('kelas', 'nama'),
            ])
            ->recordActions([
                Action::make('isiJawaban')
                    ->label(fn (PesertaUjian $record): string => $record->jawaban === null ? 'Isi Jawaban' : 'Ubah Jawaban')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->bolehMengisi())
                    ->modalHeading(fn (PesertaUjian $record): string => "{$record->siswa->nama_lengkap} · Paket {$record->paket}")
                    ->modalDescription('Ketik huruf yang dihitamkan siswa. Kosongkan semua isian bila siswa tidak hadir.')
                    ->modalWidth('5xl')
                    ->fillForm(fn (PesertaUjian $record): array => ['jawaban' => $record->jawaban ?? []])
                    ->schema(fn (PesertaUjian $record): array => static::isianJawaban($this->ujian(), $record->paket))
                    ->action(function (PesertaUjian $record, array $data): void {
                        $jawaban = array_map(fn (mixed $j): mixed => is_numeric($j) ? (float) $j : (PenilaianUjian::rapikan($j) ?: null), $data['jawaban'] ?? []);
                        $record->update(['jawaban' => array_filter($jawaban, fn (mixed $j): bool => $j !== null) === [] ? null : $jawaban]);
                        PenilaianUjian::nilaiUlang($this->ujian(), $record);
                    })
                    ->successNotificationTitle('Jawaban disimpan'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analisis')
                ->label('Analisis Butir')
                ->icon(Heroicon::OutlinedChartBar)
                ->url(fn (): string => UjianResource::getUrl('analisis', ['record' => $this->ujian()])),
            Action::make('unduh')
                ->label('Unduh Nilai (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->unduhNilai()),
        ];
    }

    /**
     * One text box per question of the packet, numbered as printed and grouped by kind.
     *
     * @return list<Fieldset>
     */
    public static function isianJawaban(Ujian $ujian, string $paket): array
    {
        return array_map(fn (array $bagian): Fieldset => Fieldset::make($bagian['jenis']->getLabel())
            ->columns($bagian['jenis'] === JenisSoal::PilihanGanda ? ['default' => 4, 'md' => 8, 'lg' => 10] : ['default' => 2, 'md' => 4, 'lg' => 6])
            ->schema(array_map(function (array $butir) use ($bagian): TextInput {
                $isian = TextInput::make("jawaban.{$butir['soal']->id}")->label((string) $butir['nomor']);
                $huruf = implode('', array_column($butir['kanan'] ?: $butir['opsi'], 'huruf'));

                return match ($bagian['jenis']) {
                    JenisSoal::Esai => $isian->numeric()->minValue(0)->maxValue($butir['bobot'])->suffix("/ {$butir['bobot']}"),
                    JenisSoal::PilihanGanda => $isian->maxLength(1)->regex("/^[{$huruf}]?$/i"),
                    JenisSoal::PgKompleks => $isian->regex("/^[{$huruf}\\s,]*$/i")->placeholder('mis. AC'),
                    JenisSoal::BenarSalah => $isian->regex('/^[BS]*$/i')->maxLength(count($butir['opsi']))->placeholder(str_repeat('B', count($butir['opsi'])))->hint(count($butir['opsi']).' pernyataan'),
                    JenisSoal::Menjodohkan => $isian->regex('/^['.implode('', array_column($butir['kanan'], 'huruf')).']*$/i')->maxLength(count($butir['opsi']))->hint(count($butir['opsi']).' baris'),
                };
            }, $bagian['butir'])), NaskahUjian::susun($ujian, $paket));
    }

    protected function unduhNilai(): StreamedResponse
    {
        $ujian = $this->ujian();
        $peserta = $ujian->peserta()->with(['siswa', 'kelas'])->get()->sortBy([['kelas.nama', 'asc'], ['siswa.nama_lengkap', 'asc']]);

        return response()->streamDownload(function () use ($peserta): void {
            $keluar = new SplFileObject('php://output', 'w');
            $keluar->fwrite("\u{FEFF}"); // Excel needs the BOM to read UTF-8 names.
            $keluar->fputcsv(['NIS', 'Nama', 'Kelas', 'Paket', 'Nilai'], ';');

            foreach ($peserta as $p) {
                $keluar->fputcsv([$p->siswa->nis, $p->siswa->nama_lengkap, $p->kelas?->nama, $p->paket, $p->nilai === null ? '' : str_replace('.', ',', $p->nilai)], ';');
            }
        }, str($ujian->nama.' '.$ujian->mataPelajaran->nama)->slug().'-nilai.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
