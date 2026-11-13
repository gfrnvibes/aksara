<?php

namespace App\Filament\Ujian\Resources\Ujians;

use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Concerns\AksesGuruAtauIzin;
use App\Filament\Ujian\Resources\Ujians\Pages\CreateUjian;
use App\Filament\Ujian\Resources\Ujians\Pages\EditUjian;
use App\Filament\Ujian\Resources\Ujians\Pages\ListUjians;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Exams put together from the question bank and printed on paper. Same access as the bank ({@see AksesGuruAtauIzin}).
 */
class UjianResource extends Resource
{
    use AksesGuruAtauIzin;

    protected static ?string $model = Ujian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Ujian';

    protected static ?string $pluralModelLabel = 'Ujian';

    public const string PETUNJUK = "1. Berdoalah sebelum mengerjakan soal.\n2. Tulis nama, kelas, dan nomor peserta pada lembar jawaban.\n3. Bacalah setiap soal dengan teliti sebelum menjawab.\n4. Kerjakan soal yang dianggap mudah terlebih dahulu.\n5. Periksa kembali jawaban sebelum diserahkan kepada pengawas.";

    protected static function izin(): string
    {
        return 'Ujian';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Ujian')
                    ->columns(3)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Ujian')
                            ->datalist(['Penilaian Tengah Semester', 'Penilaian Akhir Semester', 'Penilaian Akhir Tahun', 'Asesmen Madrasah', 'Ulangan Harian'])
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->required(),
                        Select::make('mata_pelajaran_id')
                            ->label('Mata Pelajaran')
                            ->options(static::pilihanMapel(...))
                            ->searchable()
                            ->live()
                            ->required(),
                        Select::make('tingkat')
                            ->label('Kelas')
                            ->options(KelasResource::getTingkatOptions())
                            ->live()
                            ->required(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                            ->required(),
                        DatePicker::make('tanggal')
                            ->label('Tanggal Ujian'),
                        TextInput::make('durasi_menit')
                            ->label('Waktu')
                            ->suffix('menit')
                            ->numeric()
                            ->integer()
                            ->minValue(5)
                            ->maxValue(300)
                            ->default(90)
                            ->required(),
                        Select::make('jumlah_paket')
                            ->label('Paket Soal')
                            ->options([1 => '1 paket (A)', 2 => '2 paket (A, B)', 3 => '3 paket (A–C)', 4 => '4 paket (A–D)'])
                            ->default(1)
                            ->selectablePlaceholder(false)
                            ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Paket B dan seterusnya berisi soal yang sama, dengan urutan soal dan pilihan jawaban diacak.')
                            ->required(),
                        Textarea::make('petunjuk')
                            ->label('Petunjuk Umum')
                            ->default(static::PETUNJUK)
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
                Section::make('Soal')
                    ->description('Hanya soal berstatus siap dari mapel dan kelas di atas yang bisa dipilih. Soal juga bisa dimasukkan dari Bank Soal: centang soalnya, lalu pilih "Masukkan ke Ujian". Di naskah, soal dikelompokkan per jenis.')
                    ->schema([
                        Repeater::make('butir')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('urutan')
                            ->table([
                                TableColumn::make('Soal'),
                                TableColumn::make('Bobot')->width('7rem'),
                            ])
                            ->schema([
                                Select::make('soal_id')
                                    ->options(fn (Get $get): array => static::pilihanSoal($get('../../mata_pelajaran_id'), $get('../../tingkat')))
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required(),
                                TextInput::make('bobot')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Tambah soal'),
                    ]),
            ]);
    }

    /**
     * The ready questions of a subject and grade, labelled for the picker.
     *
     * @return array<int, string>
     */
    public static function pilihanSoal(mixed $mataPelajaranId, mixed $tingkat): array
    {
        if (blank($mataPelajaranId) || blank($tingkat)) {
            return [];
        }

        // Every row of the exam's question list asks for these options; load them once per request.
        return once(fn (): array => Soal::query()
            ->select(['id', 'jenis', 'pertanyaan'])
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->where('tingkat', $tingkat)
            ->where('is_siap', true)
            ->orderBy('jenis')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Soal $soal): array => [$soal->id => "[{$soal->jenis->getLabel()}] ".str(html_entity_decode(strip_tags($soal->pertanyaan)))->squish()->limit(110)])
            ->all());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['mataPelajaran', 'tahunAjaran'])->withCount('butir'))
            ->columns([
                TextColumn::make('nama')
                    ->description(fn (Ujian $record): string => "{$record->tahunAjaran->nama} · {$record->semester->getLabel()}")
                    ->searchable(),
                TextColumn::make('mataPelajaran.nama')
                    ->label('Mapel')
                    ->description(fn (Ujian $record): string => 'Kelas '.(KelasResource::getTingkatOptions()[$record->tingkat] ?? $record->tingkat)),
                TextColumn::make('tanggal')
                    ->date('d-m-Y')
                    ->placeholder('-'),
                TextColumn::make('butir_count')
                    ->label('Soal'),
                TextColumn::make('jumlah_paket')
                    ->label('Paket'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ...array_map(fn (string $paket): Action => Action::make("naskah{$paket}")
                        ->label("Naskah Paket {$paket}")
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (Ujian $record): bool => in_array($paket, $record->daftarPaket(), true))
                        ->url(fn (Ujian $record): string => route('ujian.cetak', ['ujian' => $record, 'dokumen' => 'naskah', 'paket' => $paket]), shouldOpenInNewTab: true),
                        range('A', chr(64 + Ujian::PAKET_MAKS))),
                    Action::make('kunci')
                        ->label('Kunci Jawaban')
                        ->icon(Heroicon::OutlinedKey)
                        ->url(fn (Ujian $record): string => route('ujian.cetak', ['ujian' => $record, 'dokumen' => 'kunci']), shouldOpenInNewTab: true),
                    Action::make('kisiKisi')
                        ->label('Kisi-kisi')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->url(fn (Ujian $record): string => route('ujian.cetak', ['ujian' => $record, 'dokumen' => 'kisi-kisi']), shouldOpenInNewTab: true),
                ])->label('Cetak')->icon(Heroicon::OutlinedPrinter)->button()->color('gray'),
                ViewAction::make()->hidden(fn (Ujian $record): bool => static::canEdit($record)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUjians::route('/'),
            'create' => CreateUjian::route('/create'),
            'edit' => EditUjian::route('/{record}/edit'),
        ];
    }
}
