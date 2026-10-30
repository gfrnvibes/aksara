@php
    /** @var \App\Models\SuratKeluar $surat */
@endphp

{{-- The same letter body as the printout, on a scaled A4 sheet. Styles are scoped so they need no theme build. --}}
<style>
    .pratinjau-surat-wadah { background: var(--gray-100); padding: 1rem; overflow-x: auto; }
    .dark .pratinjau-surat-wadah { background: var(--gray-800); }
    .pratinjau-surat { width: 100%; max-width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 16mm; background: #fff; color: #000; box-shadow: 0 1px 3px rgb(0 0 0 / 0.15); font-family: "Times New Roman", Times, serif; font-size: 12px; line-height: 1.5; }
    .pratinjau-surat .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 14px; }
    .pratinjau-surat .kop .instansi { font-size: 1.1em; font-weight: bold; }
    .pratinjau-surat .kop .madrasah { font-size: 1.5em; font-weight: bold; }
    .pratinjau-surat .kop .alamat { font-style: italic; font-size: 0.92em; }
    .pratinjau-surat table { border-collapse: collapse; width: 100%; }
    .pratinjau-surat table.kepala-surat { margin-bottom: 14px; }
    .pratinjau-surat table.kepala-surat td { padding: 1px 4px 1px 0; vertical-align: top; }
    .pratinjau-surat .tujuan-surat { margin-bottom: 14px; }
    .pratinjau-surat .isi-surat p { margin: 0 0 8px; text-align: justify; }
    .pratinjau-surat .isi-surat table { width: auto; margin: 4px 0 10px 24px; }
    .pratinjau-surat .isi-surat table td { padding: 1px 8px 1px 0; vertical-align: top; }
    .pratinjau-surat .isi-surat table td p { margin: 0; }
    .pratinjau-surat .isi-surat ol, .pratinjau-surat .isi-surat ul { margin: 0 0 8px; padding-left: 22px; }
    .pratinjau-surat table.ttd-surat { margin-top: 18px; }
    .pratinjau-surat table.ttd-surat td { width: 50%; text-align: center; vertical-align: top; }
    .pratinjau-surat table.ttd-surat .ruang { height: 64px; }
</style>

<div class="pratinjau-surat-wadah">
    <div class="pratinjau-surat">
        @include('cetak.partials.kop')
        @include('cetak.partials.badan-surat-keluar', ['surat' => $surat])
    </div>
</div>
