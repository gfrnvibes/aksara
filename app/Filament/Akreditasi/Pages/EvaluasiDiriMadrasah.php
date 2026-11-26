<?php

namespace App\Filament\Akreditasi\Pages;

use App\Models\EdmBukti;
use App\Models\EdmEvaluasi;
use App\Models\EdmIndikator;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\KatalogEdm2026;
use App\Services\Akreditasi\PenyusunEdmAi;
use App\Services\Akreditasi\SinkronisasiEdmService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EvaluasiDiriMadrasah extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Evaluasi Diri (EDM)';

    protected static ?string $title = 'Evaluasi Diri Madrasah (EDM 2026)';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.akreditasi.pages.evaluasi-diri-madrasah';

    public ?int $tahunAjaranId = null;

    public string $aspekAktif = 'A';

    /** @var array<int, string> */
    public array $kondisiInput = [];

    /** @var array<int, string> */
    public array $tindakLanjutInput = [];

    /** @var array<int, string> */
    public array $catatanAiInput = [];

    /** @var array<int, TemporaryUploadedFile|null> */
    public array $berkasUpload = [];

    /** @var array<int, string> */
    public array $judulUpload = [];

    public function mount(): void
    {
        $tahun = TahunAjaran::query()->where('is_aktif', true)->first()
            ?? TahunAjaran::query()->orderByDesc('nama')->first();

        $this->tahunAjaranId = $tahun?->id;

        if ($tahun !== null) {
            $this->muatEvaluasi($tahun);
        }
    }

    public function updatedTahunAjaranId(): void
    {
        $tahun = TahunAjaran::query()->find($this->tahunAjaranId);
        if ($tahun !== null) {
            $this->muatEvaluasi($tahun);
        }
    }

    public function segarkanData(): void
    {
        $evaluasi = $this->evaluasi();
        if ($evaluasi === null) {
            return;
        }

        app(SinkronisasiEdmService::class)->sinkronkanSemua($evaluasi);
        $this->isiStateInput($evaluasi->fresh('indikators') ?? $evaluasi);

        Notification::make()
            ->success()
            ->title('Data EDM disegarkan')
            ->body('Metrik dan saran skor otomatis telah diperbarui dari data Aksara terbaru.')
            ->send();
    }

    public function pilihSkor(int $indikatorId, int $skor): void
    {
        $skor = max(1, min(4, $skor));
        $indikator = EdmIndikator::query()->findOrFail($indikatorId);
        $indikator->forceFill([
            'skor_final' => $skor,
            'pengisi_id' => auth()->id(),
        ])->save();

        Notification::make()
            ->success()
            ->title("Butir {$indikator->kode} disimpan: Tingkat {$skor}")
            ->send();
    }

    public function simpanNarasi(int $indikatorId): void
    {
        $indikator = EdmIndikator::query()->findOrFail($indikatorId);
        $indikator->forceFill([
            'kondisi_nyata' => trim((string) ($this->kondisiInput[$indikatorId] ?? '')),
            'tindak_lanjut' => trim((string) ($this->tindakLanjutInput[$indikatorId] ?? '')),
            'pengisi_id' => auth()->id(),
        ])->save();

        Notification::make()
            ->success()
            ->title("Catatan butir {$indikator->kode} disimpan")
            ->send();
    }

    public function bantuAi(int $indikatorId): void
    {
        $indikator = EdmIndikator::query()->findOrFail($indikatorId);
        $catatan = trim((string) ($this->catatanAiInput[$indikatorId] ?? ''));

        $hasil = app(PenyusunEdmAi::class)->sarankanIndikator($indikator, $catatan);

        $indikator->forceFill([
            'skor_final' => $indikator->skor_final ?? $hasil['skor'],
            'kondisi_nyata' => $hasil['kondisi_nyata'],
            'tindak_lanjut' => $hasil['tindak_lanjut'],
            'dibantu_ai' => $hasil['dibantu_ai'],
            'pengisi_id' => auth()->id(),
        ])->save();

        $this->kondisiInput[$indikatorId] = $hasil['kondisi_nyata'];
        $this->tindakLanjutInput[$indikatorId] = $hasil['tindak_lanjut'];

        Notification::make()
            ->success()
            ->title("Draf butir {$indikator->kode} berhasil disusun")
            ->body($hasil['dibantu_ai'] ? 'Disusun oleh AI Gemini berdasarkan data nyata Aksara.' : 'Disusun menggunakan templat lokal berbasis data Aksara.')
            ->send();
    }

    public function unggahBukti(int $indikatorId): void
    {
        $file = $this->berkasUpload[$indikatorId] ?? null;
        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()->warning()->title('Pilih file gambar atau PDF terlebih dahulu')->send();

            return;
        }

        $this->validate([
            "berkasUpload.{$indikatorId}" => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $indikator = EdmIndikator::query()->findOrFail($indikatorId);
        $disk = EdmIndikator::diskName();
        $path = $file->store("edm/{$indikator->kode}", $disk);

        $judul = trim((string) ($this->judulUpload[$indikatorId] ?? ''));
        if ($judul === '') {
            $judul = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: "Bukti {$indikator->kode}";
        }

        $indikator->buktis()->create([
            'judul' => $judul,
            'berkas_path' => (string) $path,
            'user_id' => auth()->id(),
        ]);

        $this->berkasUpload[$indikatorId] = null;
        $this->judulUpload[$indikatorId] = '';

        Notification::make()
            ->success()
            ->title("Bukti untuk {$indikator->kode} berhasil diunggah")
            ->send();
    }

    public function hapusBukti(int $buktiId): void
    {
        $bukti = EdmBukti::query()->findOrFail($buktiId);
        $disk = EdmIndikator::diskName();
        if (Storage::disk($disk)->exists($bukti->berkas_path)) {
            Storage::disk($disk)->delete($bukti->berkas_path);
        }
        $bukti->delete();

        Notification::make()->success()->title('Bukti dihapus')->send();
    }

    public function evaluasi(): ?EdmEvaluasi
    {
        if ($this->tahunAjaranId === null) {
            return null;
        }

        return EdmEvaluasi::query()
            ->with(['indikators.buktis', 'tahunAjaran'])
            ->where('tahun_ajaran_id', $this->tahunAjaranId)
            ->first();
    }

    /** @return array<int, string> */
    public function opsiTahunAjaran(): array
    {
        return TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all();
    }

    protected function muatEvaluasi(TahunAjaran $tahun): void
    {
        $userId = auth()->id();
        $evaluasi = app(SinkronisasiEdmService::class)->siapkanEvaluasi($tahun, is_int($userId) ? $userId : null);
        $this->isiStateInput($evaluasi);
    }

    protected function isiStateInput(EdmEvaluasi $evaluasi): void
    {
        foreach ($evaluasi->indikators as $indikator) {
            $this->kondisiInput[$indikator->id] = (string) ($indikator->kondisi_nyata ?? '');
            $this->tindakLanjutInput[$indikator->id] = (string) ($indikator->tindak_lanjut ?? '');
        }
    }

    /** @return array<string, string> */
    public function daftarAspek(): array
    {
        return KatalogEdm2026::ASPEK;
    }
}
