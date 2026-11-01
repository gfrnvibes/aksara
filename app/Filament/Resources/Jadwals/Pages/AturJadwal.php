<?php

namespace App\Filament\Resources\Jadwals\Pages;

use App\Enums\Hari;
use App\Enums\SifatPermintaanJadwal;
use App\Enums\StatusJadwal;
use App\Filament\Resources\Jadwals\JadwalResource;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\PembagianMengajar;
use App\Models\PermintaanJadwal;
use App\Services\Jadwal\PemeriksaJadwal;
use App\Services\Jadwal\PenyusunJadwal;
use App\Services\Jadwal\PolaJam;
use App\Services\Jadwal\TampilanJadwal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The timetable grid: build it automatically, then click any period to change it by hand. Periods placed by hand
 * are locked, so a later automatic build keeps them.
 */
class AturJadwal extends Page
{
    use InteractsWithRecord;

    protected static string $resource = JadwalResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(JadwalResource::canEdit($this->jadwal()), 403);
    }

    protected function jadwal(): Jadwal
    {
        $jadwal = $this->getRecord();

        return $jadwal instanceof Jadwal ? $jadwal : abort(404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Jadwal '.$this->jadwal()->judul();
    }

    public function getSubheading(): ?string
    {
        return $this->jadwal()->status === StatusJadwal::Terbit
            ? 'Sudah terbit dan tampil di panel guru. Perubahan langsung terlihat oleh guru.'
            : 'Draf. Klik sel untuk mengisi atau mengubah. Terbitkan jika sudah siap agar tampil di panel guru.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.jadwal.atur-jadwal')
                ->viewData(fn (): array => [
                    'tampilan' => TampilanJadwal::untuk($this->jadwal()),
                    'pemeriksaan' => (new PemeriksaJadwal(PolaJam::muat()))->periksa($this->jadwal()),
                ]),
        ]);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatOtomatis')
                ->label('Buat Otomatis')
                ->icon(Heroicon::OutlinedSparkles)
                ->requiresConfirmation()
                ->modalHeading('Buat jadwal otomatis?')
                ->modalDescription('Semua sel yang tidak terkunci akan disusun ulang dari Pembagian Mengajar dan Permintaan Jadwal guru. Sel terkunci (yang diisi manual) tetap di tempatnya. Proses ini bisa memakan waktu hingga beberapa detik.')
                ->modalSubmitActionLabel('Susun Jadwal')
                ->action(function (): void {
                    $hasil = (new PenyusunJadwal(PolaJam::muat()))->susun($this->jadwal());
                    $pesan = collect($hasil['laporan'])->where('tingkat', '!=', PemeriksaJadwal::INFO)->pluck('pesan');

                    $notifikasi = Notification::make()
                        ->title($hasil['berhasil'] ? 'Jadwal berhasil disusun' : 'Jadwal belum dapat disusun')
                        ->body($hasil['berhasil']
                            ? ($pesan->isEmpty() ? 'Semua pelajaran terpasang tanpa catatan.' : "{$pesan->count()} catatan, lihat bagian Pemeriksaan di bawah jadwal.")
                            : $pesan->join(' '))
                        ->color($hasil['berhasil'] ? 'success' : 'danger')
                        ->icon($hasil['berhasil'] ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle);

                    if (! $hasil['berhasil']) {
                        $notifikasi->persistent();
                    }

                    $notifikasi->send();
                }),
            ActionGroup::make([
                Action::make('cetak')
                    ->label('Cetak di browser')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (): string => route('jadwal.cetak', $this->jadwal()))
                    ->openUrlInNewTab(),
                Action::make('unduhPdf')
                    ->label('Unduh PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (): string => route('jadwal.cetak', ['jadwal' => $this->jadwal(), 'format' => 'pdf'])),
            ])
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->button(),
            Action::make('terbitkan')
                ->label('Terbitkan')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('success')
                ->visible(fn (): bool => $this->jadwal()->status === StatusJadwal::Draf)
                ->requiresConfirmation()
                ->modalDescription('Jadwal ini akan tampil di panel guru. Jadwal lain yang sudah terbit untuk semester yang sama dikembalikan menjadi draf.')
                ->action(function (): void {
                    $this->jadwal()->terbitkan();

                    Notification::make()->success()->title('Jadwal diterbitkan')->send();
                }),
            Action::make('tarik')
                ->label('Kembalikan ke Draf')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->jadwal()->status === StatusJadwal::Terbit)
                ->requiresConfirmation()
                ->modalDescription('Jadwal tidak lagi tampil di panel guru sampai diterbitkan kembali.')
                ->action(fn () => $this->jadwal()->update(['status' => StatusJadwal::Draf, 'diterbitkan_pada' => null])),
            ActionGroup::make([
                Action::make('kunciSemua')
                    ->label('Kunci semua sel')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->action(fn () => $this->jadwal()->isi()->update(['is_terkunci' => true])),
                Action::make('lepasKunci')
                    ->label('Lepas semua kunci')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->action(fn () => $this->jadwal()->isi()->update(['is_terkunci' => false])),
                Action::make('kosongkan')
                    ->label('Kosongkan jadwal')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Seluruh isi jadwal ini, termasuk sel terkunci, akan dihapus.')
                    ->action(fn () => $this->jadwal()->isi()->delete()),
            ])
                ->label('Lainnya')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Fill, change or empty one period of a class; opened by clicking a cell of the grid.
     */
    public function aturSelAction(): Action
    {
        return Action::make('aturSel')
            ->modalWidth('lg')
            ->modalHeading(fn (array $arguments): string => $this->judulSel($arguments))
            ->fillForm(fn (array $arguments): array => [
                'pembagian_mengajar_id' => $this->selDari($arguments)?->pembagian_mengajar_id,
                'is_terkunci' => $this->selDari($arguments)->is_terkunci ?? true,
            ])
            ->schema(fn (array $arguments): array => [
                Select::make('pembagian_mengajar_id')
                    ->label('Pelajaran')
                    ->options($this->opsiPelajaran((int) ($arguments['kelas'] ?? 0)))
                    ->placeholder('(Kosong)')
                    ->searchable(),
                Toggle::make('is_terkunci')
                    ->label('Kunci sel ini')
                    ->helperText('Sel terkunci tidak diubah saat jadwal disusun ulang otomatis.'),
            ])
            ->modalSubmitActionLabel('Simpan')
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->simpanSel($arguments, $data, $action);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $data
     */
    protected function simpanSel(array $arguments, array $data, Action $action): void
    {
        $kelasId = (int) ($arguments['kelas'] ?? 0);
        $hari = Hari::from((int) ($arguments['hari'] ?? 0));
        $jpKe = (int) ($arguments['jp'] ?? 0);
        $sel = $this->selDari($arguments);

        if (blank($data['pembagian_mengajar_id'] ?? null)) {
            $sel?->delete();

            return;
        }

        $tugas = PembagianMengajar::query()
            ->with(['gtk', 'mataPelajaran'])
            ->where('tahun_ajaran_id', $this->jadwal()->tahun_ajaran_id)
            ->where('kelas_id', $kelasId)
            ->findOrFail((int) $data['pembagian_mengajar_id']);

        $bentrok = $this->jadwal()->isi()
            ->with('kelas')
            ->where('hari', $hari)
            ->where('jp_ke', $jpKe)
            ->where('kelas_id', '!=', $kelasId)
            ->whereHas('pembagianMengajar', fn ($query) => $query->where('gtk_id', $tugas->gtk_id))
            ->first();

        if ($bentrok !== null) {
            Notification::make()
                ->danger()
                ->title('Bentrok')
                ->body("{$tugas->gtk->nama} sudah mengajar di kelas {$bentrok->kelas->nama} pada {$hari->getLabel()} JP {$jpKe}.")
                ->send();

            $action->halt();
        }

        IsiJadwal::query()->updateOrCreate(
            ['jadwal_id' => $this->jadwal()->id, 'kelas_id' => $kelasId, 'hari' => $hari, 'jp_ke' => $jpKe],
            ['pembagian_mengajar_id' => $tugas->id, 'is_terkunci' => (bool) ($data['is_terkunci'] ?? false)],
        );

        $dilanggar = PermintaanJadwal::query()
            ->where('gtk_id', $tugas->gtk_id)
            ->where('is_aktif', true)
            ->get()
            ->filter(fn (PermintaanJadwal $permintaan): bool => $permintaan->dilanggarOleh($hari, $jpKe, $tugas->mata_pelajaran_id));

        if ($dilanggar->isNotEmpty()) {
            $wajib = $dilanggar->contains('sifat', SifatPermintaanJadwal::Wajib);

            Notification::make()
                ->color($wajib ? 'danger' : 'warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->title('Tersimpan, tetapi melanggar permintaan guru')
                ->body($dilanggar->map(fn (PermintaanJadwal $permintaan): string => $permintaan->ringkasan())->join('; '))
                ->send();
        }
    }

    /**
     * The class's teaching assignments, labelled with their code and how many periods are already placed.
     *
     * @return array<int, string>
     */
    protected function opsiPelajaran(int $kelasId): array
    {
        $tampilan = TampilanJadwal::untuk($this->jadwal());
        $terpasang = $this->jadwal()->isi()->where('kelas_id', $kelasId)->get()->countBy('pembagian_mengajar_id');

        return PembagianMengajar::query()
            ->with(['gtk', 'mataPelajaran'])
            ->where('tahun_ajaran_id', $this->jadwal()->tahun_ajaran_id)
            ->where('kelas_id', $kelasId)
            ->get()
            ->sortBy(fn (PembagianMengajar $tugas): string => $tampilan->kode[$tugas->id]['kode'] ?? '')
            ->mapWithKeys(fn (PembagianMengajar $tugas): array => [$tugas->id => sprintf(
                '%s · %s · %s (%d/%d JP)',
                $tampilan->kode[$tugas->id]['kode'] ?? '?',
                $tugas->mataPelajaran->nama,
                $tugas->gtk->nama,
                $terpasang[$tugas->id] ?? 0,
                $tugas->jp_per_minggu,
            )])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function selDari(array $arguments): ?IsiJadwal
    {
        return $this->jadwal()->isi()
            ->where('kelas_id', (int) ($arguments['kelas'] ?? 0))
            ->where('hari', (int) ($arguments['hari'] ?? 0))
            ->where('jp_ke', (int) ($arguments['jp'] ?? 0))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function judulSel(array $arguments): string
    {
        $hari = Hari::tryFrom((int) ($arguments['hari'] ?? 0));
        $jpKe = (int) ($arguments['jp'] ?? 0);
        $kelas = Kelas::query()->find((int) ($arguments['kelas'] ?? 0));
        $jam = $hari === null ? null : PolaJam::muat()->jamUntuk($hari, $jpKe);

        return "Kelas {$kelas?->nama} · {$hari?->getLabel()} JP {$jpKe}".($jam ? " ({$jam->rentang()})" : '');
    }
}
