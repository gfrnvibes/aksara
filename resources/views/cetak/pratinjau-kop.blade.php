<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Pratinjau Kop Surat</title>
<style>@page { size: A4; margin: 18mm; } body { font-family: DejaVu Serif, serif; font-size: 10pt; color: #000; } * { box-sizing: border-box; }</style>
</head><body>
@include('cetak.partials.kop', ['kop' => $kop])
<div style="text-align: center; font-weight: bold; margin: 8mm 0;">CONTOH DOKUMEN</div>
<p>Ini adalah pratinjau kop pada kertas A4. Periksa ukuran logo, keterbacaan teks, dan jarak sebelum menerapkan kop sebagai default.</p>
</body></html>
