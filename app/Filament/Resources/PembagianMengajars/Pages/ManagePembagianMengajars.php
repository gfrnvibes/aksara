<?php

namespace App\Filament\Resources\PembagianMengajars\Pages;

use App\Filament\Resources\PembagianMengajars\PembagianMengajarResource;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManagePembagianMengajars extends ManageRecords
{
    protected static string $resource = PembagianMengajarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiSalinTahunLalu(),
            $this->aksiTambah(),
        ];
    }

    /**
     * Assign one subject to a teacher in several classes at once, the way the teaching schedule is drawn up.
     */
    protected function aksiTambah(): Action
    {
        return Action::make('tambah')
            ->label('Tambah Pembagian Mengajar')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize('create', PembagianMengajar::class)
            ->modalWidth('lg')
            ->schema([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
                Select::make('gtk_id')
                    ->label('Guru')
                    ->options(fn (): array => Gtk::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('mata_pelajaran_id')
                    ->label('Mata Pelajaran')
                    ->options(fn (): array => MataPelajaran::query()->aktifBerurutan()->pluck('nama', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('kelas_ids')
                    ->label('Kelas')
                    ->helperText('Pilih semua kelas yang diajar guru ini untuk mata pelajaran tersebut.')
                    ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                    ->multiple()
                    ->required(),
                TextInput::make('jp_per_minggu')
                    ->label('JP per Minggu (tiap kelas)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(2)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $sudahAda = PembagianMengajar::query()
                    ->with(['kelas', 'gtk'])
                    ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
                    ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
                    ->whereIn('kelas_id', $data['kelas_ids'])
                    ->get()
                    ->keyBy('kelas_id');

                $dibuat = 0;

                DB::transaction(function () use ($data, $sudahAda, &$dibuat): void {
                    foreach ($data['kelas_ids'] as $kelasId) {
                        if ($sudahAda->has((int) $kelasId)) {
                            continue;
                        }

                        PembagianMengajar::query()->create([
                            'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                            'gtk_id' => $data['gtk_id'],
                            'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                            'kelas_id' => $kelasId,
                            'jp_per_minggu' => $data['jp_per_minggu'],
                        ]);

                        $dibuat++;
                    }
                });

                $notifikasi = Notification::make()->success()->title("{$dibuat} pembagian mengajar ditambahkan");

                if ($sudahAda->isNotEmpty()) {
                    $notifikasi->warning()
                        ->body('Dilewati karena sudah ada gurunya: '.$sudahAda->map(fn (PembagianMengajar $p): string => "{$p->kelas->nama} ({$p->gtk->nama})")->join(', ').'. Ubah barisnya jika ingin mengganti guru.')
                        ->persistent();
                }

                $notifikasi->send();
            });
    }

    /**
     * Start a new academic year from last year's assignments, then adjust what changed.
     */
    protected function aksiSalinTahunLalu(): Action
    {
        return Action::make('salin')
            ->label('Salin dari Tahun Ajaran Lain')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->authorize('create', PembagianMengajar::class)
            ->modalWidth('md')
            ->modalDescription('Pembagian mengajar guru yang masih aktif disalin ke tahun ajaran tujuan. Mata pelajaran di kelas yang sudah punya guru di tahun tujuan tidak diubah.')
            ->schema([
                Select::make('dari_tahun_ajaran_id')
                    ->label('Salin dari')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->required()
                    ->different('ke_tahun_ajaran_id'),
                Select::make('ke_tahun_ajaran_id')
                    ->label('Ke tahun ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $sudahAda = PembagianMengajar::query()
                    ->where('tahun_ajaran_id', $data['ke_tahun_ajaran_id'])
                    ->get(['kelas_id', 'mata_pelajaran_id'])
                    ->map(fn (PembagianMengajar $p): string => "{$p->kelas_id}-{$p->mata_pelajaran_id}")
                    ->flip();

                $sumber = PembagianMengajar::query()
                    ->where('tahun_ajaran_id', $data['dari_tahun_ajaran_id'])
                    ->whereHas('gtk', fn ($gtk) => $gtk->where('is_aktif', true))
                    ->whereHas('kelas', fn ($kelas) => $kelas->where('is_aktif', true))
                    ->get();

                $disalin = 0;

                DB::transaction(function () use ($sumber, $sudahAda, $data, &$disalin): void {
                    foreach ($sumber as $pembagian) {
                        if ($sudahAda->has("{$pembagian->kelas_id}-{$pembagian->mata_pelajaran_id}")) {
                            continue;
                        }

                        $pembagian->replicate()->fill(['tahun_ajaran_id' => $data['ke_tahun_ajaran_id']])->save();
                        $disalin++;
                    }
                });

                Notification::make()->success()->title("{$disalin} pembagian mengajar disalin")->send();
            });
    }
}
