<?php

namespace App\Filament\Ujian\Resources\KegiatanUjians\Pages;

use App\Filament\Ujian\Resources\KegiatanUjians\KegiatanUjianResource;
use App\Models\KegiatanUjian;
use App\Models\Kelas;
use App\Models\PenempatanUjian;
use App\Models\RuangUjian;
use App\Models\User;
use App\Services\Cbt\PemetaanDenahUjian;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class KelolaRuangDanDenah extends Page
{
    use InteractsWithRecord;

    protected static string $resource = KegiatanUjianResource::class;

    protected string $view = 'filament.ujian.kelola-ruang-dan-denah';

    protected static ?string $title = 'Ruang & Denah Ujian';

    public ?int $ruangAktifId = null;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof KegiatanUjian
            ? KegiatanUjianResource::can('view', $record)
            : KegiatanUjianResource::canViewAny();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $kegiatan = $this->kegiatan();
        abort_unless(KegiatanUjianResource::can('view', $kegiatan), 403);

        $this->ruangAktifId = $kegiatan->ruang()->value('id');
    }

    public function kegiatan(): KegiatanUjian
    {
        $record = $this->getRecord();
        abort_unless($record instanceof KegiatanUjian, 404);

        return $record;
    }

    public function bolehKelola(): bool
    {
        $user = Filament::auth()->user();

        return ($user instanceof User && $user->hasRole(config('filament-shield.super_admin.name')))
            || KegiatanUjianResource::can('update', $this->kegiatan());
    }

    public function getSubheading(): ?string
    {
        $kegiatan = $this->kegiatan();
        $jumlahRuang = $kegiatan->ruang()->count();
        $jumlahPeserta = $kegiatan->penempatan()->count();

        return "{$kegiatan->nama} · {$jumlahRuang} Ruang · {$jumlahPeserta} Peserta Terpetakan";
    }

    /**
     * @return Collection<int, RuangUjian>
     */
    public function daftarRuang(): Collection
    {
        return $this->kegiatan()->ruang()
            ->with(['penempatan' => fn ($q) => $q->with(['siswa', 'kelas'])->orderBy('urutan_posisi')->orderBy('nomor_kursi')])
            ->get();
    }

    public function ruangAktif(): ?RuangUjian
    {
        $daftar = $this->daftarRuang();
        if ($daftar->isEmpty()) {
            return null;
        }

        return $daftar->firstWhere('id', $this->ruangAktifId) ?? $daftar->first();
    }

    public function pilihRuang(int $ruangId): void
    {
        $this->ruangAktifId = $ruangId;
    }

    public function hapusRuang(int $ruangId): void
    {
        abort_unless($this->bolehKelola(), 403);

        $kegiatan = $this->kegiatan();
        $ruang = $kegiatan->ruang()->whereKey($ruangId)->firstOrFail();
        $ruang->delete();

        $this->ruangAktifId = $kegiatan->ruang()->value('id');

        Notification::make()->success()->title('Ruang ujian dihapus')->send();
    }

    public function editRuangAction(): Action
    {
        return Action::make('editRuang')
            ->label('Edit Ruang')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->size('xs')
            ->visible(fn (): bool => $this->bolehKelola())
            ->fillForm(function (array $arguments): array {
                $ruangId = (int) ($arguments['ruang'] ?? $this->ruangAktifId ?? 0);
                $ruang = $this->kegiatan()->ruang()->whereKey($ruangId)->firstOrFail();

                return $ruang->only(['kode', 'nama', 'urutan', 'jumlah_kolom', 'kursi_per_meja', 'kapasitas']);
            })
            ->schema($this->skemaFormRuang(isEdit: true))
            ->action(function (array $data, array $arguments): void {
                abort_unless($this->bolehKelola(), 403);

                $ruangId = (int) ($arguments['ruang'] ?? $this->ruangAktifId ?? 0);
                $ruang = $this->kegiatan()->ruang()->whereKey($ruangId)->firstOrFail();
                $ruang->update($data);

                $this->ruangAktifId = $ruang->id;
                Notification::make()->success()->title("Ruang {$ruang->nama} diperbarui")->send();
            });
    }

    public function hapusRuangAction(): Action
    {
        return Action::make('hapusRuang')
            ->label('Hapus Ruang')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading('Hapus Ruang Ujian')
            ->modalDescription('Peserta yang sudah duduk di ruang ini akan dilepas penempatannya. Anda bisa menjalankan Generate & Petakan Denah lagi setelahnya.')
            ->visible(fn (): bool => $this->bolehKelola())
            ->action(function (array $arguments): void {
                $ruangId = (int) ($arguments['ruang'] ?? $this->ruangAktifId ?? 0);
                $this->hapusRuang($ruangId);
            });
    }

    /**
     * @return list<Component>
     */
    protected function skemaFormRuang(bool $isEdit = false): array
    {
        $fields = [
            TextInput::make('kode')
                ->label('Kode Ruang')
                ->placeholder('R-01')
                ->default(fn (): string => 'R-'.str_pad((string) ($this->kegiatan()->ruang()->count() + 1), 2, '0', STR_PAD_LEFT))
                ->required()
                ->maxLength(20),
            TextInput::make('nama')
                ->label('Nama Ruang')
                ->placeholder('Ruang 01 - Kelas VII A')
                ->default(fn (): string => 'Ruang '.str_pad((string) ($this->kegiatan()->ruang()->count() + 1), 2, '0', STR_PAD_LEFT))
                ->required()
                ->maxLength(100),
            TextInput::make('jumlah_kolom')
                ->label('Jumlah Kolom Meja')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(8)
                ->default(4)
                ->required(),
            Select::make('kursi_per_meja')
                ->label('Kursi per Meja')
                ->options([
                    1 => '1 Kursi per Meja',
                    2 => '2 Kursi per Meja (Kiri - Kanan)',
                ])
                ->default(2)
                ->required(),
            TextInput::make('kapasitas')
                ->label('Kapasitas Kursi')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100)
                ->default(32)
                ->required(),
        ];

        if ($isEdit) {
            $fields[] = TextInput::make('urutan')
                ->label('Urutan Ruang')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required();
        }

        return $fields;
    }

    protected function getHeaderActions(): array
    {
        return [

            Action::make('petakanPeserta')
                ->label('Generate & Petakan Denah')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->visible(fn (): bool => $this->bolehKelola())
                ->schema([
                    Select::make('kelas_ids')
                        ->label('Filter Kelas (kosongkan untuk semua siswa aktif)')
                        ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                        ->multiple()
                        ->searchable(),
                    Select::make('mode')
                        ->label('Pola Penempatan Kursi')
                        ->options([
                            'selang_seling' => '2 Kursi Selang-Seling Beda Tingkat',
                            'berurutan' => 'Berurutan per Kelas',
                        ])
                        ->default('selang_seling')
                        ->required(),
                    TextInput::make('prefix')
                        ->label('Prefix Nomor Peserta')
                        ->default(fn (): ?string => $this->kegiatan()->prefix_nomor_peserta)
                        ->placeholder('26-')
                        ->maxLength(30),
                ])
                ->action(function (array $data): void {
                    try {
                        $jumlah = app(PemetaanDenahUjian::class)->petakan(
                            $this->kegiatan(),
                            array_values(array_map('intval', $data['kelas_ids'] ?? [])),
                            (string) $data['mode'],
                            filled($data['prefix'] ?? null) ? (string) $data['prefix'] : null,
                        );

                        Notification::make()->success()->title("{$jumlah} peserta berhasil dipetakan ke ruang ujian")->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first() ?? 'Gagal memetakan peserta')->send();
                    }
                }),

            Action::make('tambahRuang')
                ->label('Tambah Ruang')
                ->icon(Heroicon::OutlinedPlus)
                ->color(Color::Sky)
                ->visible(fn (): bool => $this->bolehKelola())
                ->schema($this->skemaFormRuang())
                ->action(function (array $data): void {
                    $kegiatan = $this->kegiatan();
                    $ruang = $kegiatan->ruang()->create([
                        ...$data,
                        'urutan' => $kegiatan->ruang()->count() + 1,
                    ]);

                    $this->ruangAktifId = $ruang->id;
                    Notification::make()->success()->title("Ruang {$ruang->nama} ditambahkan")->send();
                }),

            Action::make('tukarKursi')
                ->label('Tukar Kursi')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('warning')
                ->visible(fn (): bool => $this->bolehKelola() && $this->kegiatan()->penempatan()->count() >= 2)
                ->schema([
                    Select::make('peserta_a')
                        ->label('Peserta Pertama')
                        ->options(fn (): array => $this->opsiPeserta())
                        ->searchable()
                        ->required(),
                    Select::make('peserta_b')
                        ->label('Peserta Kedua')
                        ->options(fn (): array => $this->opsiPeserta())
                        ->searchable()
                        ->different('peserta_a')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $kegiatan = $this->kegiatan();
                    $a = PenempatanUjian::query()->where('kegiatan_ujian_id', $kegiatan->id)->whereKey((int) $data['peserta_a'])->firstOrFail();
                    $b = PenempatanUjian::query()->where('kegiatan_ujian_id', $kegiatan->id)->whereKey((int) $data['peserta_b'])->firstOrFail();

                    app(PemetaanDenahUjian::class)->tukarKursi($a, $b);

                    Notification::make()->success()->title('Posisi kursi berhasil ditukar')->send();
                }),

            ActionGroup::make([
                Action::make('cetakKartu')
                    ->label('Kartu Peserta')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->visible(fn (): bool => $this->kegiatan()->penempatan()->exists())
                    ->schema([
                        Select::make('ruang_id')
                            ->label('Filter Ruang (opsional)')
                            ->options(fn (): array => $this->kegiatan()->ruang()->pluck('nama', 'id')->all())
                            ->placeholder('Semua Ruang'),
                        Select::make('kelas_id')
                            ->label('Filter Kelas (opsional)')
                            ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                            ->placeholder('Semua Kelas'),
                    ])
                    ->action(function (array $data): void {
                        $params = array_filter([
                            'ruang_id' => $data['ruang_id'] ?? null,
                            'kelas_id' => $data['kelas_id'] ?? null,
                        ]);

                        $this->redirect(route('ujian.kartu', ['kegiatan' => $this->kegiatan(), ...$params]));
                    }),
                Action::make('cetakDenah')
                    ->label('Denah Ruang')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->visible(fn (): bool => $this->kegiatan()->ruang()->exists())
                    ->url(fn (): string => route('ujian.denah', $this->kegiatan()))
                    ->openUrlInNewTab(),
                Action::make('cetakDaftarPeserta')
                    ->label('Daftar Peserta Ruangan')
                    ->icon(Heroicon::OutlinedUsers)
                    ->visible(fn (): bool => $this->kegiatan()->ruang()->exists())
                    ->url(fn (): string => route('ujian.daftar-peserta', $this->kegiatan()))
                    ->openUrlInNewTab(),
                Action::make('cetakDaftarHadir')
                    ->label('Daftar Hadir Peserta')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->visible(fn (): bool => $this->kegiatan()->ruang()->exists())
                    ->url(fn (): string => route('ujian.daftar-hadir', $this->kegiatan()))
                    ->openUrlInNewTab(),
                Action::make('cetakSemuanya')
                    ->label('Semuanya (Kartu + Denah + Daftar Hadir)')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (): bool => $this->kegiatan()->ruang()->exists())
                    ->url(fn (): string => route('ujian.semuanya', $this->kegiatan()))
                    ->openUrlInNewTab(),
            ])
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->color(Color::Indigo)
                ->button()
                ->visible(fn (): bool => $this->kegiatan()->ruang()->exists() || $this->kegiatan()->penempatan()->exists()),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function opsiPeserta(): array
    {
        return $this->kegiatan()->penempatan()
            ->with(['siswa', 'ruangUjian', 'kelas'])
            ->orderBy('ruang_ujian_id')
            ->orderBy('nomor_kursi')
            ->get()
            ->mapWithKeys(fn (PenempatanUjian $p): array => [
                $p->id => "{$p->nomor_peserta} · {$p->siswa->nama_lengkap} ({$p->kelas?->nama} — {$p->ruangUjian?->kode} Kursi {$p->nomor_kursi})",
            ])
            ->all();
    }
}
