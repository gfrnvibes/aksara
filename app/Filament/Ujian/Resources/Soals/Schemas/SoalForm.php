<?php

namespace App\Filament\Ujian\Resources\Soals\Schemas;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\Soal;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class SoalForm
{
    /**
     * @param  Closure(): array<int, string>  $pilihanMapel  The subjects the user may write questions for.
     */
    public static function configure(Schema $schema, Closure $pilihanMapel): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Soal')
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
                    ->schema([
                        Select::make('mata_pelajaran_id')
                            ->label('Mata Pelajaran')
                            ->options($pilihanMapel)
                            // A new question opened from a subject's list starts in that subject and grade.
                            ->default(fn ($livewire): mixed => data_get($livewire, 'mapelAwal'))
                            ->searchable()
                            ->required(),
                        Select::make('tingkat')
                            ->label('Kelas')
                            ->options(KelasResource::getTingkatOptions())
                            ->default(fn ($livewire): mixed => data_get($livewire, 'tingkatAwal'))
                            ->required(),
                        Select::make('jenis')
                            ->label('Jenis Soal')
                            ->options(JenisSoal::class)
                            ->default(JenisSoal::PilihanGanda)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->required(),
                        Select::make('level_kognitif')
                            ->label('Level Kognitif')
                            ->options(LevelKognitif::class),
                        TextInput::make('materi')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['md' => 1, 'lg' => 2]),
                        Textarea::make('indikator')
                            ->label('Indikator Soal')
                            ->rows(1)
                            ->autosize()
                            ->columnSpan(['md' => 1, 'lg' => 2]),
                    ]),
                Section::make('Soal')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])
                            ->schema([
                                RichEditor::make('pertanyaan')
                                    ->label('Pertanyaan')
                                    ->toolbarButtons([['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                                    ->required()
                                    ->extraFieldWrapperAttributes(['class' => 'soal-pertanyaan'])
                                    ->columnSpan(['lg' => 2]),
                                // The editor beside it stretches to this height (see theme.css).
                                static::unggahGambar('gambar')
                                    ->label('Gambar (opsional)')
                                    ->panelAspectRatio('16:10'),
                            ]),
                        Repeater::make('opsi')
                            ->label(fn (Get $get): string => static::jenis($get)->labelOpsi())
                            ->hidden(fn (Get $get): bool => static::jenis($get) === JenisSoal::Esai)
                            ->table(fn (Get $get): array => static::kolomOpsi(static::jenis($get)))
                            ->schema(fn (Get $get): array => static::isianOpsi(static::jenis($get)))
                            ->compact()
                            ->defaultItems(4)
                            ->minItems(fn (Get $get): int => static::jenis($get)->batasOpsi()[0])
                            ->maxItems(fn (Get $get): int => static::jenis($get)->batasOpsi()[1])
                            ->addActionLabel('Tambah baris')
                            ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (($pesan = Soal::kesalahanKunci(static::jenis($get), (array) $value)) !== null) {
                                    $fail($pesan);
                                }
                            }]),
                        Textarea::make('pembahasan')
                            ->label(fn (Get $get): string => static::jenis($get) === JenisSoal::Esai ? 'Kunci & Pedoman Penskoran' : 'Pembahasan (opsional)')
                            ->rows(2)
                            ->autosize()
                            ->required(fn (Get $get): bool => static::jenis($get) === JenisSoal::Esai),
                    ]),
                Toggle::make('is_siap')
                    ->label('Siap dipakai')
                    ->helperText('Soal draf, termasuk buatan AI, tidak bisa dipilih untuk ujian sampai diperiksa dan ditandai siap.'),
            ]);
    }

    /**
     * @return list<TableColumn>
     */
    protected static function kolomOpsi(JenisSoal $jenis): array
    {
        return match ($jenis) {
            JenisSoal::PilihanGanda, JenisSoal::PgKompleks => [
                TableColumn::make('Pilihan Jawaban'),
                TableColumn::make('Gambar (opsional)')->width('11rem'),
                TableColumn::make('Benar')->width('5rem')->alignment(Alignment::Center),
            ],
            JenisSoal::BenarSalah => [TableColumn::make('Pernyataan'), TableColumn::make('Benar')->width('5rem')->alignment(Alignment::Center)],
            JenisSoal::Menjodohkan => [TableColumn::make('Pernyataan (kiri)'), TableColumn::make('Jawaban (kanan)')],
            JenisSoal::Esai => [],
        };
    }

    /**
     * One field per column of {@see kolomOpsi()}: a table repeater keeps a cell even for a hidden field.
     *
     * @return list<Field>
     */
    protected static function isianOpsi(JenisSoal $jenis): array
    {
        $teks = Textarea::make('teks')->hiddenLabel()->rows(1)->autosize();
        $benar = Toggle::make('benar')->hiddenLabel()->extraFieldWrapperAttributes(['class' => 'soal-tengah']);

        return match ($jenis) {
            JenisSoal::PilihanGanda, JenisSoal::PgKompleks => [
                // A choice may be an image alone.
                $teks->required(fn (Get $get): bool => blank($get('gambar'))),
                static::unggahGambar('gambar')->hiddenLabel()->panelLayout('compact')->imagePreviewHeight('60'),
                $benar,
            ],
            JenisSoal::BenarSalah => [$teks->required(), $benar],
            JenisSoal::Menjodohkan => [$teks->required(), TextInput::make('pasangan')->hiddenLabel()->required()],
            JenisSoal::Esai => [],
        };
    }

    protected static function jenis(Get $get, string $path = 'jenis'): JenisSoal
    {
        $jenis = $get($path);

        return $jenis instanceof JenisSoal ? $jenis : (JenisSoal::tryFrom((string) $jenis) ?? JenisSoal::PilihanGanda);
    }

    protected static function unggahGambar(string $nama): FileUpload
    {
        return FileUpload::make($nama)
            ->image()
            ->disk(fn (): string => Soal::disk())
            ->directory('soal')
            ->visibility('private')
            ->maxSize(1024);
    }
}
