<?php

namespace App\Filament\Bendahara\Pages;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TagihanSiswa as Tagihan;
use App\Models\TahunAjaran;
use App\Services\Keuangan\PencatatanKeuangan;
use App\Services\Keuangan\RingkasanTagihan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TagihanSiswa extends HalamanKeuangan implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Tagihan Siswa';

    public function getSubheading(): ?string
    {
        return 'Satu baris per nama tagihan, mencakup semua periode dan tahun ajaran. Klik baris untuk melihat siswa penerima. Total tidak mencakup tagihan yang dibatalkan.';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('buatTagihan')->label('Buat Tagihan')->schema([
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->options(fn () => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id'))->default(fn () => TahunAjaran::query()->where('is_aktif', true)->value('id'))->required()->rules(['exists:tahun_ajarans,id']),
            Select::make('siswa_id')->label('Siswa (kosongkan untuk satu kelas)')->options(fn () => Siswa::query()->orderBy('nama_lengkap')->get()->mapWithKeys(fn (Siswa $s): array => [$s->id => $s->nis.' - '.$s->nama_lengkap]))->searchable(),
            Select::make('kelas_id')->label('Kelas untuk tagihan massal')->options(fn () => Kelas::query()->pluck('nama', 'id'))->helperText('Digunakan jika siswa tidak dipilih. Mengikuti riwayat kelas pada tahun ajaran terpilih.'),
            TextInput::make('nama')->label('Jenis Tagihan')->placeholder('SPP / Daftar ulang / Kegiatan')->required()->maxLength(100),
            TextInput::make('periode')->placeholder('November 2026')->helperText('Nama dan periode yang sama tidak akan ditagihkan dua kali.')->required()->maxLength(50),
            TextInput::make('nominal')->numeric()->integer()->minValue(1)->maxValue(999999999999)->prefix('Rp')->required(),
            DatePicker::make('jatuh_tempo')->required(),
        ])->action(function (array $data): void {
            $user = PencatatanKeuangan::izinkan();
            $tahun = TahunAjaran::query()->whereKey($data['tahun_ajaran_id'])->firstOrFail();
            $siswa = Siswa::query();
            if (filled($data['siswa_id'] ?? null)) {
                $siswa->whereKey($data['siswa_id']);
            } elseif (filled($data['kelas_id'] ?? null)) {
                $siswa->anggotaKelas((int) $data['kelas_id'], $tahun->id);
            } else {
                throw ValidationException::withMessages(['siswa_id' => 'Pilih siswa atau kelas tujuan.']);
            }
            $ids = $siswa->pluck('id');
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['kelas_id' => 'Tidak ada siswa untuk pilihan ini.']);
            }
            $jumlah = DB::transaction(function () use ($ids, $data, $tahun, $user): int {
                $jumlah = 0;
                foreach ($ids as $id) {
                    $tagihan = Tagihan::query()->firstOrCreate([
                        'siswa_id' => $id, 'tahun_ajaran_id' => $tahun->id, 'nama' => trim($data['nama']), 'periode' => trim($data['periode']),
                    ], ['nominal' => $data['nominal'], 'jatuh_tempo' => $data['jatuh_tempo'], 'pencatat_id' => $user->id]);
                    $jumlah += (int) $tagihan->wasRecentlyCreated;
                }

                return $jumlah;
            });
            Notification::make()->success()->title("{$jumlah} tagihan dibuat")->body('Tagihan duplikat dilewati, termasuk yang pernah dibatalkan.')->send();
        })];
    }

    public function table(Table $table): Table
    {
        return $table->defaultPaginationPageOption(25)->paginationPageOptions([10, 25, 50, 100])->query(RingkasanTagihan::query())
            ->defaultSort('nama')
            ->recordUrl(fn (Tagihan $record): string => DetailTagihanSiswa::getUrl(['tagihan' => $record->id]))
            ->columns([
                TextColumn::make('nama')->label('Nama Tagihan')->searchable(['tagihan_siswas.nama'])->sortable(['tagihan_siswas.nama'])->weight('bold'),
                TextColumn::make('jumlah_siswa')->label('Siswa Ditagih')->numeric()->sortable(),
                TextColumn::make('jumlah_tagihan')->label('Jumlah Tagihan')->numeric()->sortable(),
                TextColumn::make('total_nominal')->label('Total Tagihan Aktif')->money('IDR')->sortable(),
                TextColumn::make('total_terbayar')->label('Total Terbayar')->color('success')->money('IDR')->sortable(),
                TextColumn::make('total_sisa')->label('Total Sisa')->color('danger')->money('IDR')->sortable(),
                TextColumn::make('jumlah_batal')->label('Dibatalkan')->numeric()->toggleable(),
            ])->recordActions([
                Action::make('lihatDetail')->label('Lihat Detail')->icon(Heroicon::OutlinedEye)
                    ->url(fn (Tagihan $record): string => DetailTagihanSiswa::getUrl(['tagihan' => $record->id])),
            ])->emptyStateHeading('Belum ada tagihan siswa');
    }
}
