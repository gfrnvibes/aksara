<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Jobs\BuatSoalAi as JobBuatSoalAi;
use App\Models\User;
use App\Services\Cbt\PenyusunSoalAi;
use App\Services\Gemini\KlienGemini;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Drafts many questions at once from a blueprint table, one Gemini request per row, in the background. The
 * teacher is told through the bell when the drafts are ready.
 */
class BuatSoalAi extends Page
{
    protected static string $resource = SoalResource::class;

    protected string $view = 'filament.ujian.buat-soal-ai';

    /** Rows and questions per submission, so one teacher does not spend the day's free Gemini quota. */
    public const int BARIS_MAKS = 10;

    public const int SOAL_MAKS = 50;

    public int $mapel;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return KlienGemini::aktif() && SoalResource::canCreate();
    }

    public function mount(int $mapel = 0): void
    {
        abort_unless(array_key_exists($mapel, SoalResource::pilihanMapel()), 403);

        $this->mapel = $mapel;
        $this->getSchema('form')?->fill([
            'tingkat' => request()->integer('tingkat') ?: null,
            'baris' => [['jenis' => JenisSoal::PilihanGanda->value, 'jumlah' => 5]],
        ]);
    }

    public function getTitle(): string
    {
        return 'Buat Soal dengan AI · '.SoalResource::labelKelompok($this->mapel);
    }

    public function getBreadcrumbs(): array
    {
        return [SoalResource::getUrl() => 'Bank Soal', SoalResource::urlKelompok($this->mapel) => SoalResource::labelKelompok($this->mapel), 'Buat dengan AI'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 4])
                    ->description('Satu baris untuk satu materi. Gemini hanya menerima mapel, kelas, dan isi tabel ini; setiap baris dikirim terpisah dan diproses di latar belakang. Hasilnya masuk sebagai draf dan Anda diberi tahu lewat lonceng notifikasi.')
                    ->schema([
                        Select::make('tingkat')
                            ->label('Kelas')
                            ->options(KelasResource::getTingkatOptions())
                            ->required()
                            ->columnSpan(1),
                        Repeater::make('baris')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Materi')->markAsRequired(),
                                TableColumn::make('Indikator (opsional)'),
                                TableColumn::make('Jenis')->width('13rem')->markAsRequired(),
                                TableColumn::make('Level')->width('12rem'),
                                TableColumn::make('Jumlah')->width('6rem')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('materi')->required()->maxLength(255),
                                Textarea::make('indikator')->rows(1)->autosize()->maxLength(1000),
                                Select::make('jenis')->options(JenisSoal::class)->selectablePlaceholder(false)->required(),
                                Select::make('level_kognitif')->options(LevelKognitif::class)->placeholder('Campuran'),
                                TextInput::make('jumlah')->numeric()->integer()->minValue(1)->maxValue(PenyusunSoalAi::JUMLAH_MAKS)->required(),
                            ])
                            ->compact()
                            ->minItems(1)
                            ->maxItems(static::BARIS_MAKS)
                            ->cloneable()
                            ->addActionLabel('Tambah baris')
                            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (array_sum(array_map(fn (mixed $b): int => (int) (is_array($b) ? ($b['jumlah'] ?? 0) : 0), (array) $value)) > static::SOAL_MAKS) {
                                    $fail('Paling banyak '.static::SOAL_MAKS.' soal sekali kirim.');
                                }
                            }])
                            ->helperText(fn (Get $get): string => 'Total: '.array_sum(array_map(fn (mixed $b): int => (int) (is_array($b) ? ($b['jumlah'] ?? 0) : 0), (array) $get('baris'))).' soal (maks. '.static::SOAL_MAKS.')')
                            ->live(onBlur: true),
                    ]),
            ]);
    }

    public function kirim(): void
    {
        $data = $this->getSchema('form')?->getState() ?? [];
        $user = Filament::auth()->user();
        abort_unless($user instanceof User && array_key_exists($this->mapel, SoalResource::pilihanMapel()), 403);

        $tingkat = (int) $data['tingkat'];
        $jobs = array_map(fn (array $baris): JobBuatSoalAi => new JobBuatSoalAi($user->id, $this->mapel, $tingkat, [
            'materi' => trim($baris['materi']),
            'indikator' => filled($baris['indikator'] ?? null) ? trim($baris['indikator']) : null,
            'jenis' => $baris['jenis'] instanceof JenisSoal ? $baris['jenis']->value : (string) $baris['jenis'],
            'level_kognitif' => $baris['level_kognitif'] instanceof LevelKognitif ? $baris['level_kognitif']->value : ($baris['level_kognitif'] ?? null),
            'jumlah' => (int) $baris['jumlah'],
        ]), array_values($data['baris']));

        $url = SoalResource::urlKelompok($this->mapel, $tingkat);
        $label = SoalResource::labelKelompok($this->mapel, $tingkat);
        $userId = $user->id;

        Bus::batch($jobs)
            ->name("Soal AI {$label}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($userId, $url, $label): void {
                $jumlah = (int) Cache::pull(JobBuatSoalAi::kunciJumlah($batch->id), 0);
                $gagal = (array) Cache::pull(JobBuatSoalAi::kunciGagal($batch->id), []);
                $penerima = User::query()->find($userId);

                if ($penerima === null) {
                    return;
                }

                // A row stopped by the worker (e.g. a timeout) fails without naming itself; count those too.
                $tanpaNama = max(0, $batch->failedJobs - count($gagal));
                $pesanGagal = implode(', ', [...$gagal, ...($tanpaNama > 0 ? ["{$tanpaNama} baris lain"] : [])]);

                Notification::make()
                    ->title("{$jumlah} draf soal dibuat untuk {$label}")
                    ->body($pesanGagal === '' ? 'Periksa setiap soal dan kuncinya, lalu tandai siap dipakai.' : "Gagal dibuat: {$pesanGagal}. Coba kirim ulang baris tersebut.")
                    ->status($pesanGagal === '' ? 'success' : 'warning')
                    ->actions([Action::make('lihat')->label('Lihat soal')->url($url)->markAsRead()])
                    ->sendToDatabase($penerima);
            })
            ->dispatch();

        Notification::make()->success()
            ->title('Permintaan dikirim ke AI')
            ->body(count($jobs).' baris sedang diproses. Anda akan diberi tahu lewat lonceng notifikasi setelah selesai.')
            ->send();

        $this->redirect($url, navigate: true);
    }
}
