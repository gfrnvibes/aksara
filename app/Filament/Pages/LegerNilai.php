<?php

namespace App\Filament\Pages;

use App\Enums\Semester;
use App\Exports\LegerExport;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Services\Leger\Leger;
use App\Services\Leger\SusunLeger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The grade recap of a class for one semester, with every subject's scores, the total, the average and the
 * rank, downloadable as Excel or PDF for the promotion meeting.
 *
 * @property-read Schema $form
 */
class LegerNilai extends Page
{
    /**
     * The Shield permission generated for this page.
     */
    public const string IZIN = 'View:LegerNilai';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Buku Induk';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Leger Nilai';

    protected static ?string $title = 'Leger Nilai';

    protected static ?string $slug = 'leger-nilai';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(static::IZIN) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            View::make('filament.pages.leger-nilai')
                ->viewData(fn (): array => ['leger' => $this->leger()]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->live(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                            ->live(),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn (): array => Kelas::query()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                            ->live(),
                    ]),
            ]);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('unduhExcel')
                    ->label('Excel (.xlsx)')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(fn (): ?BinaryFileResponse => ($leger = $this->leger())
                        ? response()
                            ->download(LegerExport::excel($leger), $leger->namaFile('xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->deleteFileAfterSend()
                        : null),
                Action::make('unduhPdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->action(function (): ?StreamedResponse {
                        $leger = $this->leger();

                        if ($leger === null) {
                            return null;
                        }

                        $pdf = LegerExport::pdf($leger);

                        return response()->streamDownload(function () use ($pdf): void {
                            echo $pdf;
                        }, $leger->namaFile('pdf'), ['Content-Type' => 'application/pdf']);
                    }),
            ])
                ->label('Unduh Leger')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->hidden(fn (): bool => $this->leger() === null),
        ];
    }

    /**
     * The recap for the chosen class, year and semester, or null while one of them is not chosen.
     */
    public function leger(): ?Leger
    {
        $kelas = filled($this->data['kelas_id'] ?? null) ? Kelas::query()->find((int) $this->data['kelas_id']) : null;
        $tahunAjaran = filled($this->data['tahun_ajaran_id'] ?? null) ? TahunAjaran::query()->find((int) $this->data['tahun_ajaran_id']) : null;
        $semester = $this->data['semester'] ?? null;
        $semester = $semester instanceof Semester ? $semester : Semester::tryFrom((int) $semester);

        if ($kelas === null || $tahunAjaran === null || $semester === null) {
            return null;
        }

        return once(fn (): Leger => app(SusunLeger::class)->untuk($kelas, $tahunAjaran, $semester));
    }
}
