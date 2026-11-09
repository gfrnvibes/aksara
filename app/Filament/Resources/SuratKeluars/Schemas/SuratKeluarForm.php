<?php

namespace App\Filament\Resources\SuratKeluars\Schemas;

use App\Enums\JenisSuratKeluar;
use App\Enums\SifatSurat;
use App\Filament\Resources\KopSurats\KopSuratResource;
use App\Filament\Resources\PenandaTangans\PenandaTanganResource;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\PenandaTangan;
use App\Models\Siswa;
use App\Models\SuratKeluar;
use App\Models\TahunAjaran;
use App\Services\Gemini\KlienGemini;
use App\Services\Surat\IsianSurat;
use App\Services\Surat\PengaturanKop;
use App\Services\Surat\PenomoranSurat;
use App\Services\Surat\PenyusunSuratAi;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The letter on the left — a request to Gemini, the letter's details and its body — and, on the right, an A4
 * preview of exactly what will be printed, refreshed as the fields change.
 */
class SuratKeluarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Group::make([
                            static::sectionAi(),
                            static::sectionDetail(),
                            Section::make('Isi Surat')
                                ->headerActions([static::petunjuk('petunjukIsi', 'Gunakan tombol Tanda Isian di kanan atas untuk menyalin data otomatis ke isi surat, perihal, tujuan, atau lampiran.')])
                                ->schema([
                                    RichEditor::make('isi')
                                        ->hiddenLabel()
                                        ->toolbarButtons([
                                            ['bold', 'italic', 'underline'],
                                            ['alignStart', 'alignCenter', 'alignJustify'],
                                            ['bulletList', 'orderedList'],
                                            ['table', 'tableAddRowAfter', 'tableDeleteRow', 'tableDelete'],
                                            ['undo', 'redo'],
                                        ])
                                        ->live(onBlur: true)
                                        ->required(),
                                ]),
                        ]),
                        Section::make('Pratinjau')
                            ->headerActions([static::petunjuk('petunjukPratinjau', 'Tampilan sama dengan hasil cetak dan diperbarui setelah isian selesai diubah.')])
                            ->schema([
                                View::make('filament.surat.pratinjau')
                                    ->viewData(fn (Get $get, ?SuratKeluar $record): array => ['surat' => static::suratDariIsian($get, $record)]),
                            ]),
                    ]),
            ]);
    }

    public static function aksiKopSurat(): Action
    {
        return Action::make('aturKop')->label('Kop Surat')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
            ->tooltip(fn (?SuratKeluar $record): string => 'Kop: '.(($record->kop_surat_snapshot ?? PengaturanKop::untuk('surat_keluar'))['sumber_nama'] ?? 'Kop bawaan madrasah').'. Buka pengaturan di tab baru. Perubahan default tidak mengubah kop surat yang sudah tersimpan.')
            ->url(fn (): string => KopSuratResource::getUrl())->openUrlInNewTab()->visible(fn (): bool => KopSuratResource::canViewAny());
    }

    public static function aksiTandaIsian(): Action
    {
        return Action::make('tandaIsian')->label('Tanda Isian')->icon(Heroicon::OutlinedCodeBracket)->color('gray')
            ->tooltip('Lihat dan salin tanda untuk mengisi data surat secara otomatis.')
            ->modalHeading('Tanda Isian Surat')->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
            ->modalDescription('Klik tanda untuk menyalin, lalu tempel di isi surat, perihal, tujuan, atau lampiran. Data siswa dan orang tua mengikuti siswa yang dipilih.')
            ->modalContent(fn () => view('filament.surat.daftar-tanda-isian', ['tanda' => IsianSurat::TANDA]));
    }

    protected static function petunjuk(string $name, string $teks): Action
    {
        return Action::make($name)->label('Petunjuk')->icon(Heroicon::OutlinedInformationCircle)->iconButton()->color('gray')->tooltip($teks);
    }

    protected static function sectionAi(): Section
    {
        return Section::make('Buat dengan AI')
            ->icon(Heroicon::Sparkles)
            ->headerActions([static::petunjuk('petunjukAi', 'Tulis kebutuhan surat atau perintah revisi. Jangan sertakan data pribadi siswa, NIK, atau nomor HP; gunakan pilihan Siswa dan Tanda Isian untuk data pribadi.')])
            ->collapsible()
            ->visible(fn (): bool => KlienGemini::aktif())
            ->schema([
                Textarea::make('perintah')
                    ->hiddenLabel()
                    ->placeholder('Tulis kebutuhan surat atau perintah revisi…')
                    ->rows(3)
                    ->dehydrated(false),
                Actions::make([static::aksiBuatDenganAi()])->key('aksiAi'),
            ]);
    }

    protected static function sectionDetail(): Section
    {
        return Section::make('Detail Surat')
            ->columns(2)
            ->schema([
                Select::make('jenis')
                    ->label('Jenis Surat')
                    ->options(JenisSuratKeluar::class)
                    ->default(JenisSuratKeluar::Undangan)
                    ->disabledOn('edit')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Jenis dan nomor tidak berubah setelah surat diberi nomor.')
                    ->live()
                    ->required(),
                DatePicker::make('tanggal')
                    ->label('Tanggal Surat')
                    ->default(now())
                    ->live()
                    ->required(),
                Toggle::make('gunakan_sifat')
                    ->label('Cantumkan sifat surat')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Menampilkan baris Sifat di kepala surat.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                        if (! $state) {
                            $set('sifat', null);
                        }
                    }),
                Toggle::make('tanda_tangan_kepala_madrasah')
                    ->label('Tanda tangan kepala madrasah')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Matikan bila surat tidak ditandatangani kepala madrasah.')
                    ->default(true)
                    ->live(),
                Select::make('sifat')
                    ->label('Sifat Surat')
                    ->options(SifatSurat::class)
                    ->placeholder('Pilih sifat surat')
                    ->visible(fn (Get $get): bool => (bool) $get('gunakan_sifat'))
                    ->live(),
                TextInput::make('lampiran')
                    ->placeholder('-')
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->columnSpan(fn (Get $get): int|string => $get('gunakan_sifat') ? 1 : 'full'),
                TextInput::make('perihal')
                    ->live(onBlur: true)
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('tujuan')
                    ->label('Kepada Yth.')
                    ->placeholder('Bapak/Ibu Orang Tua/Wali Siswa Kelas IX')
                    ->live(onBlur: true)
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('penanda_tangan_id')
                    ->label('Penanda Tangan Tambahan (opsional)')
                    ->relationship('penandaTangan', 'nama', fn (Builder $query, ?SuratKeluar $record): Builder => $query
                        ->scopes(['bukanKepalaMadrasah'])
                        ->where(fn (Builder $query) => $query->where('is_aktif', true)->when($record?->penanda_tangan_id, fn (Builder $query, int $id) => $query->orWhereKey($id)))
                        ->orderBy('urutan')
                        ->orderBy('nama'))
                    ->getOptionLabelFromRecordUsing(fn (PenandaTangan $record): string => $record->label())
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: fn (Get $get): string => static::keteranganKepala($get))
                    ->placeholder('Hanya kepala madrasah')
                    ->preload()
                    ->createOptionForm(PenandaTanganResource::isianUtama())
                    ->live(),
                Select::make('siswa_id')
                    ->label('Siswa (opsional)')
                    ->relationship('siswa', 'nama_lengkap', fn (Builder $query): Builder => $query->orderBy('nama_lengkap'))
                    ->getOptionLabelFromRecordUsing(fn (Siswa $record): string => "{$record->nis} - {$record->nama_lengkap}")
                    ->searchable(['nama_lengkap', 'nis', 'nisn'])
                    ->preload()
                    ->optionsLimit(SiswaResource::BATAS_PILIHAN)
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Pilih siswa untuk mengisi tanda data siswa dan orang tua, seperti {nama_siswa} dan {nama_orang_tua}.')
                    ->live(),
            ]);
    }

    protected static function aksiBuatDenganAi(): Action
    {
        return Action::make('buatDenganAi')
            ->label(fn (Get $get): string => filled(strip_tags((string) $get('isi'))) ? 'Revisi dengan AI' : 'Buat dengan AI')
            ->icon(Heroicon::Sparkles)
            ->action(function (Get $get, Set $set, ?SuratKeluar $record): void {
                $perintah = trim((string) $get('perintah'));

                if ($perintah === '') {
                    Notification::make()->warning()->title('Tulis dulu surat yang ingin dibuat')->send();

                    return;
                }

                try {
                    $surat = app(PenyusunSuratAi::class)->susun($perintah, [
                        'nama_madrasah' => (string) config('sekolah.nama'),
                        'wilayah' => config('sekolah.wilayah'),
                        'tanggal_hari_ini' => now()->translatedFormat('l, d F Y'),
                        'jabatan_penanda_tangan' => 'Kepala Madrasah',
                    ], $get('isi'));
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('Surat belum bisa dibuat dengan AI')
                        ->body(ucfirst(KlienGemini::alasanGagal($exception)).'. Coba lagi beberapa saat lagi, atau tulis isi surat secara manual.')
                        ->send();

                    return;
                }

                // A numbered letter keeps its kind, because the kind is part of its number.
                if ($surat['jenis'] !== null && $record === null) {
                    $set('jenis', $surat['jenis']);
                }

                if ($surat['sifat'] !== null) {
                    $set('gunakan_sifat', true);
                    $set('sifat', $surat['sifat']);
                } else {
                    $set('gunakan_sifat', false);
                    $set('sifat', null);
                }

                $set('perihal', $surat['perihal']);
                $set('tujuan', $surat['tujuan']);
                $set('lampiran', $surat['lampiran']);
                $set('isi', $surat['isi']);

                Notification::make()
                    ->success()
                    ->title('Surat dibuat dengan Gemini')
                    ->body('Periksa pratinjau dan sesuaikan isinya sebelum menyimpan.')
                    ->send();
            });
    }

    /**
     * Who signs as principal for the letter's date, shown under the additional-signer choice.
     */
    protected static function keteranganKepala(Get $get): string
    {
        $tahunAjaran = TahunAjaran::untukTanggal(filled($get('tanggal')) ? Carbon::parse($get('tanggal')) : now());

        if (blank($tahunAjaran?->kepala_madrasah)) {
            return 'Isi nama kepala madrasah di menu Tahun Ajaran agar tercetak saat tanda tangannya diaktifkan. Pilih pejabat tambahan jika diperlukan.';
        }

        return "Kepala madrasah ({$tahunAjaran->kepala_madrasah}, Tahun Ajaran {$tahunAjaran->nama}) aktif default, tetapi bisa dinonaktifkan bila perlu. Pilih bila surat juga ditandatangani pejabat lain.";
    }

    /**
     * An unsaved letter holding the form's current values, so the preview renders it exactly like the printout.
     */
    public static function suratDariIsian(Get $get, ?SuratKeluar $record): SuratKeluar
    {
        $surat = $record?->replicate() ?? new SuratKeluar;
        $jenis = $get('jenis');
        $jenis = $jenis instanceof JenisSuratKeluar ? $jenis : (JenisSuratKeluar::tryFrom((string) $jenis) ?? JenisSuratKeluar::Lainnya);
        $gunakanSifat = (bool) $get('gunakan_sifat');
        $sifat = $get('sifat');
        $tanggal = filled($get('tanggal')) ? Carbon::parse($get('tanggal')) : now();

        $surat->jenis = $jenis;
        $surat->gunakan_sifat = $gunakanSifat;
        $surat->tanda_tangan_kepala_madrasah = (bool) $get('tanda_tangan_kepala_madrasah');
        $surat->sifat = $gunakanSifat ? ($sifat instanceof SifatSurat ? $sifat : SifatSurat::tryFrom((string) $sifat)) : null;
        $surat->tanggal = $tanggal;
        $surat->perihal = (string) $get('perihal');
        $surat->tujuan = (string) $get('tujuan');
        $surat->lampiran = $get('lampiran');
        $surat->isi = $get('isi');
        $surat->nomor = $record->nomor ?? app(PenomoranSurat::class)->pratinjauNomor($jenis, $tanggal);

        // A saved letter keeps the principal copied when it was written, unless its date moves to another year.
        if ($record === null || ! $record->tanggal->isSameDay($tanggal)) {
            $surat->salinKepalaMadrasah();
        }

        $surat->penanda_tangan_id = filled($get('penanda_tangan_id')) ? (int) $get('penanda_tangan_id') : null;
        $surat->salinPenandaTanganTambahan();

        $surat->setRelation('siswa', filled($get('siswa_id')) ? Siswa::withTrashed()->find($get('siswa_id')) : null);

        return $surat;
    }
}
