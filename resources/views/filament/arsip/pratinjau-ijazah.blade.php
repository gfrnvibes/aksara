@php
    /** @var list<array{url: string, pdf: bool, nama: string}> $berkas */
@endphp

<div style="display: grid; gap: 1rem;">
    @foreach ($berkas as $file)
        <div style="border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; border-bottom: 1px solid var(--gray-200); font-size: 0.75rem; color: var(--gray-600);">
                <span>Halaman {{ $loop->iteration }} dari {{ count($berkas) }} &middot; {{ $file['pdf'] ? 'PDF' : 'Gambar' }}</span>
                <a href="{{ $file['url'] }}" target="_blank" rel="noopener" style="color: var(--primary-600); font-weight: 600;">Buka di tab baru</a>
            </div>

            @if ($file['pdf'])
                <iframe src="{{ $file['url'] }}" title="Ijazah halaman {{ $loop->iteration }}" style="display: block; width: 100%; height: 75vh; border: 0; background: #fff;"></iframe>
            @else
                <img src="{{ $file['url'] }}" alt="Ijazah halaman {{ $loop->iteration }}" loading="lazy" style="display: block; max-width: 100%; max-height: 80vh; margin: 0 auto; background: #fff;">
            @endif
        </div>
    @endforeach

    <p style="font-size: 0.75rem; color: var(--gray-500);">
        Tautan pratinjau hanya berlaku {{ \App\Models\Ijazah::MASA_BERLAKU_TAUTAN }} menit. Buka ulang pratinjau bila tautan sudah kedaluwarsa.
    </p>
</div>
