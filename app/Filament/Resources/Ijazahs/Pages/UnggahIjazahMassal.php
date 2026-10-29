<?php

namespace App\Filament\Resources\Ijazahs\Pages;

use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Services\Arsip\ImporArsipIjazah;
use App\Services\Arsip\PencocokLulusan;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Upload a whole graduation year with any file names, then pair every file with its graduate. Files whose name
 * holds a NIS/NISN or a graduate's name are paired automatically and only need checking.
 *
 * @property-read Schema $form
 */
class UnggahIjazahMassal extends Page
{
    protected static string $resource = IjazahResource::class;

    protected static ?string $title = 'Unggah Ijazah Massal';

    protected static ?string $breadcrumb = 'Unggah Massal';

    /**
     * Most files one upload accepts.
     */
    public const int MAKS_FILE = 200;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return IjazahResource::canCreate();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('1. Pilih angkatan dan file')
                    ->description('Nama file bebas. File bernama NIS/NISN (misalnya 0133438941-1.jpg) atau nama lulusan langsung dipasangkan otomatis.')
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextInput::make('tahun_lulus')
                            ->label('Tahun Lulus')
                            ->numeric()
                            ->integer()
                            ->minValue(1950)
                            ->maxValue((int) now()->year + 1)
                            ->default((int) now()->year)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::sinkronkanBaris($get, $set, tebakUlang: true))
                            ->required(),
                        FileUpload::make('berkas')
                            ->label('File ijazah')
                            ->multiple()
                            ->maxFiles(static::MAKS_FILE)
                            ->acceptedFileTypes(IjazahResource::JENIS_BERKAS)
                            ->maxSize(IjazahResource::UKURAN_MAKS_KB)
                            ->storeFiles(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::sinkronkanBaris($get, $set))
                            ->required()
                            ->columnSpan(['md' => 3]),
                    ]),
                Section::make('2. Pasangkan dengan lulusan')
                    ->description('Periksa tebakan, pilih lulusan untuk file yang masih kosong, dan geser baris untuk mengatur urutan halaman. Beberapa file dengan lulusan yang sama menjadi halaman dari satu ijazah.')
                    ->visible(fn (Get $get): bool => $get->filled('pasangan'))
                    ->schema([
                        Repeater::make('pasangan')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Pratinjau')->width('7rem'),
                                TableColumn::make('File')->width('24%'),
                                TableColumn::make('Lulusan')->markAsRequired(),
                                TableColumn::make('Nama Lulusan Baru')->width('24%'),
                            ])
                            ->schema([
                                Hidden::make('kunci'),
                                ImageEntry::make('pratinjau')
                                    ->hiddenLabel()
                                    ->imageHeight(64)
                                    ->placeholder('PDF'),
                                TextEntry::make('nama_file')
                                    ->hiddenLabel(),
                                Select::make('lulusan')
                                    ->hiddenLabel()
                                    ->placeholder('Pilih lulusan')
                                    ->searchable()
                                    ->options(fn (Get $get): array => static::pilihanLulusan($get->integer('../../tahun_lulus')))
                                    ->live()
                                    ->required(),
                                TextInput::make('nama_baru')
                                    ->hiddenLabel()
                                    ->placeholder('Nama lengkap lulusan')
                                    ->visible(fn (Get $get): bool => $get('lulusan') === PencocokLulusan::BARU)
                                    ->required(fn (Get $get): bool => $get('lulusan') === PencocokLulusan::BARU)
                                    ->maxLength(255),
                            ])
                            ->addable(false)
                            ->deletable()
                            ->reorderable()
                            ->compact(),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('simpan')
                ->label('Simpan ke Arsip')
                ->icon(Heroicon::OutlinedCheck)
                ->disabled(fn (): bool => blank($this->data['pasangan'] ?? null))
                ->action(fn () => $this->simpan()),
        ];
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        $tahunLulus = (int) $data['tahun_lulus'];
        $berkas = static::berkasDiunggah($this->data);

        $pasangan = [];

        foreach ((array) ($data['pasangan'] ?? []) as $baris) {
            $file = $berkas[$baris['kunci'] ?? ''] ?? null;

            if ($file === null || blank($baris['lulusan'] ?? null)) {
                continue;
            }

            $pasangan[] = [
                'target' => $baris['lulusan'] === PencocokLulusan::BARU ? PencocokLulusan::BARU.':'.trim((string) $baris['nama_baru']) : (string) $baris['lulusan'],
                'file' => $file,
            ];
        }

        $hasil = app(ImporArsipIjazah::class)->simpanPasangan($tahunLulus, $pasangan);

        Notification::make()
            ->success()
            ->title("Ijazah {$tahunLulus} tersimpan")
            ->body(count($pasangan).' file: '.$hasil['dibuat'].' data baru, '.$hasil['diperbarui'].' data ditambah berkasnya.')
            ->send();

        $this->redirect(IjazahResource::getUrl('index'));
    }

    /**
     * Keep one row per uploaded file: add rows for new files with a guessed graduate, and drop rows of removed files.
     */
    protected static function sinkronkanBaris(Get $get, Set $set, bool $tebakUlang = false): void
    {
        $berkas = static::berkasDiunggah(['berkas' => $get('berkas')]);
        $tahunLulus = $get->integer('tahun_lulus');
        $pencocok = app(PencocokLulusan::class);
        $baris = [];

        foreach ((array) $get('pasangan') as $kunciBaris => $isi) {
            if (is_array($isi) && isset($berkas[$isi['kunci'] ?? ''])) {
                $baris[$kunciBaris] = $tebakUlang && blank($isi['lulusan'] ?? null) && $tahunLulus
                    ? [...$isi, 'lulusan' => $pencocok->tebak($isi['nama_file'], $tahunLulus)]
                    : $isi;
            }
        }

        $sudahAda = array_column($baris, 'kunci');

        foreach ($berkas as $kunci => $file) {
            if (in_array($kunci, $sudahAda, true)) {
                continue;
            }

            $baris[$kunci] = [
                'kunci' => $kunci,
                'pratinjau' => $file->isPreviewable() ? $file->temporaryUrl() : null,
                'nama_file' => $file->getClientOriginalName(),
                'lulusan' => $tahunLulus ? $pencocok->tebak($file->getClientOriginalName(), $tahunLulus) : null,
                'nama_baru' => null,
            ];
        }

        $set('pasangan', $baris);
    }

    /**
     * The uploaded files of the form state, keyed by their upload key.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, TemporaryUploadedFile>
     */
    protected static function berkasDiunggah(?array $data): array
    {
        return array_filter((array) ($data['berkas'] ?? []), fn (mixed $file): bool => $file instanceof TemporaryUploadedFile);
    }

    /**
     * The whole list of graduates and students, grouped, plus the choice to add a new alumnus. Computed once per
     * request because every row shows the same list.
     *
     * @return array<string, string|array<string, string>>
     */
    protected static function pilihanLulusan(?int $tahunLulus): array
    {
        return once(fn (): array => [
            PencocokLulusan::BARU => '+ Lulusan baru (isi namanya)',
            ...($tahunLulus ? app(PencocokLulusan::class)->pilihan($tahunLulus) : []),
        ]);
    }
}
