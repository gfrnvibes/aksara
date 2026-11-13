<?php

namespace App\Filament\Guru\Pages;

use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PresensiHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/** @property-read Schema $form */
class PresensiKelas extends HalamanHarian
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $title = 'Presensi Kelas';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public string $versi = '';

    public function mount(): void
    {
        $this->form->fill(['tanggal' => AksesHarian::hariIni(), 'kelas_id' => array_key_first($this->kelasOptions(AksesHarian::hariIni())), 'baris' => []]);
        $this->muat();
    }

    /** @return array<int, string> */
    public function kelasOptions(string $tanggal): array
    {
        return Kelas::query()->aktif()->when(! AksesHarian::piket(static::user(), $tanggal), fn ($q) => $q->where('wali_kelas_id', static::user()->gtk->id ?? 0))
            ->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all();
    }

    public function getSubheading(): ?string
    {
        return 'Simpan kehadiran harian. Hari yang belum diisi tetap belum tercatat; rekap S/I/A rapor mengikuti semester.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Kelas & Tanggal')->columns(2)->schema([
                DatePicker::make('tanggal')->default(AksesHarian::hariIni())->maxDate(AksesHarian::hariIni())->required()->live()->afterStateUpdated(fn () => $this->muat()),
                Select::make('kelas_id')->label('Kelas')->options(fn (): array => $this->kelasOptions((string) ($this->data['tanggal'] ?? AksesHarian::hariIni())))
                    ->required()->live()->afterStateUpdated(fn () => $this->muat()),
            ]),
            Section::make('Daftar Kehadiran')->description('Terlambat tetap berstatus hadir. Alasan koreksi wajib jika mengubah data tersimpan.')->schema([
                Repeater::make('baris')->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)->compact()
                    ->table([TableColumn::make('Nama'), TableColumn::make('Kehadiran'), TableColumn::make('Terlambat (menit)'), TableColumn::make('Catatan')])
                    ->schema([
                        Hidden::make('siswa_id'), TextInput::make('nama')->disabled()->dehydrated(false),
                        Select::make('status')->options(PresensiSiswa::STATUS)->placeholder('Belum diisi')->required(),
                        TextInput::make('terlambat_menit')->numeric()->integer()->minValue(0)->maxValue(600)->default(0)->required(),
                        TextInput::make('catatan')->maxLength(500),
                    ]),
                TextInput::make('alasan')->label('Alasan koreksi / pencatatan tanggal lampau')->maxLength(500),
            ]),
        ]);
    }

    public function muat(): void
    {
        $this->data['baris'] = [];
        $this->data['alasan'] = null;
        $tanggal = (string) ($this->data['tanggal'] ?? '');
        $kelas = Kelas::query()->find((int) ($this->data['kelas_id'] ?? 0));
        if ($kelas === null || $tanggal === '') {
            return;
        }
        abort_unless(AksesHarian::kelas(static::user(), $kelas, $tanggal), 403);
        try {
            $tahun = AksesHarian::tahun($tanggal);
        } catch (ValidationException) {
            $this->form->fill($this->data);
            Notification::make()->warning()->title('Tahun ajaran untuk tanggal ini belum tersedia')->send();

            return;
        }
        $saved = PresensiSiswa::query()->where('kelas_id', $kelas->id)->where('tanggal', $tanggal)->get()->keyBy('siswa_id');
        $this->data['baris'] = Siswa::query()->anggotaKelas($kelas->id, $tahun->id)->orderBy('nama_lengkap')->get()->map(fn (Siswa $siswa): array => [
            'siswa_id' => $siswa->id, 'nama' => $siswa->nama_lengkap, 'status' => $saved->get($siswa->id)?->status,
            'terlambat_menit' => $saved->get($siswa->id)->terlambat_menit ?? 0, 'catatan' => $saved->get($siswa->id)?->catatan,
        ])->all();
        $this->versi = app(PresensiHarian::class)->versi($kelas->id, $tanggal);
        $this->form->fill($this->data);
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        app(PresensiHarian::class)->simpanKelas(static::user(), Kelas::query()->findOrFail((int) $data['kelas_id']), $data['tanggal'], array_values($data['baris']), $this->versi, $data['alasan'] ?? null);
        $this->muat();
        Notification::make()->title('Presensi tersimpan dan rapor yang belum dikunci diperbarui')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hadirSemua')->label('Tandai semua hadir')->color('gray')->action(function (): void {
                foreach ($this->data['baris'] ?? [] as $key => $row) {
                    if (blank($row['status'] ?? null)) {
                        $this->data['baris'][$key]['status'] = 'hadir';
                    }
                }
            }),
            Action::make('muat')->label('Muat ulang')->color('gray')->action(fn () => $this->muat()),
            Action::make('simpan')->label('Simpan Presensi')->icon(Heroicon::OutlinedCheck)->action(fn () => $this->simpan()),
        ];
    }
}
