<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Surat Pengantar Usulan Kenaikan Pangkat - {{ $pegawai->nama_lengkap ?? '-' }}</title>
    <style>
        @page { size: A4; margin: 2cm; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; color: #000; margin: 0; padding: 0; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 24px; }
        .header-instansi { font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header-address { font-size: 10pt; }
        .meta-table, .data-table, .lampiran-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td, .data-table td { padding: 4px 8px; vertical-align: top; }
        .label { width: 34%; }
        .separator { width: 2%; text-align: center; }
        .section-title { font-weight: bold; margin: 18px 0 8px; }
        .lampiran-table th, .lampiran-table td { border: 1px solid #000; padding: 5px 8px; font-size: 11pt; }
        .lampiran-table th { text-align: center; background-color: #e8e8e8; }
        .signature { width: 42%; margin-left: auto; margin-top: 32px; text-align: center; }
        .signature-space { height: 64px; }
        .signature-name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
<div class="header">
    <div class="header-instansi">{{ $namaSatker }}</div>
    <div class="header-address">{{ $alamatSatker }}</div>
</div>

<table class="meta-table">
    <tr><td class="label">Nomor</td><td class="separator">:</td><td>{{ $nomorSurat }}</td></tr>
    <tr><td class="label">Perihal</td><td class="separator">:</td><td>Usulan Kenaikan Pangkat a.n. {{ $pegawai->nama_lengkap ?? '-' }}</td></tr>
</table>

<p>Dengan hormat, bersama ini kami sampaikan usulan kenaikan pangkat pegawai sebagai berikut:</p>

<div class="section-title">Data Pegawai</div>
<table class="data-table">
    <tr><td class="label">NIP</td><td class="separator">:</td><td>{{ $pegawai->nip ?? '-' }}</td></tr>
    <tr><td class="label">Nama</td><td class="separator">:</td><td>{{ $pegawai->nama_lengkap ?? '-' }}</td></tr>
    <tr><td class="label">Pangkat Saat Ini</td><td class="separator">:</td><td>{{ $usulan->pangkatAsal?->nama ?? $pegawai->pangkat?->nama ?? '-' }}</td></tr>
    <tr><td class="label">TMT</td><td class="separator">:</td><td>{{ $usulan->tmt_pangkat_asal?->translatedFormat('d F Y') ?? '-' }}</td></tr>
    <tr><td class="label">Pangkat Yang Diusulkan</td><td class="separator">:</td><td>{{ $usulan->pangkatTujuan?->nama ?? '-' }}</td></tr>
    <tr><td class="label">Unit Kerja</td><td class="separator">:</td><td>{{ $pegawai->unitKerja?->nama ?? '-' }}</td></tr>
</table>

@php
    $lampiranValid = $usulan->checklistSubmission?->items?->filter(fn ($item) => (bool) ($item->is_valid ?? false)) ?? collect();
@endphp

<div class="section-title">Daftar Berkas Terlampir</div>
<table class="lampiran-table">
    <thead><tr><th style="width: 8%;">No</th><th>Nama Berkas</th></tr></thead>
    <tbody>
    @forelse($lampiranValid as $index => $item)
        <tr><td style="text-align: center;">{{ $index + 1 }}</td><td>{{ $item->templateItem?->nama ?? $item->nama_berkas ?? '-' }}</td></tr>
    @empty
        <tr><td style="text-align: center;">-</td><td>Belum ada berkas valid yang tercatat.</td></tr>
    @endforelse
    </tbody>
</table>

<p>Demikian surat pengantar ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

<div class="signature">
    <div>{{ config('sikep.tempat_satker', 'Penajam') }}, {{ $tanggalSurat->translatedFormat('d F Y') }}</div>
    <div>{{ $pejabatPenandatangan->jabatan?->nama ?? config('sikep.penandatangan.kenaikan_pangkat', 'Pejabat Penandatangan') }}</div>
    <div class="signature-space"></div>
    <div class="signature-name">{{ $pejabatPenandatangan->nama_lengkap ?? '........................' }}</div>
    <div>NIP. {{ $pejabatPenandatangan->nip ?? '........................' }}</div>
</div>
</body>
</html>
