<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Formulir Pengajuan Cuti - {{ $p->nomor_pengajuan }}</title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header-instansi {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            text-decoration: underline;
        }

        .header-nomor {
            font-size: 10pt;
            margin-top: 5px;
        }

        .section-title {
            font-weight: bold;
            font-size: 11pt;
            background-color: #e8e8e8;
            padding: 5px 8px;
            margin: 15px 0 8px 0;
            border: 1px solid #000;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.data-table td {
            padding: 4px 8px;
            vertical-align: top;
        }

        table.data-table td.label {
            width: 35%;
            font-weight: normal;
        }

        table.data-table td.separator {
            width: 2%;
            text-align: center;
        }

        table.data-table td.value {
            width: 63%;
        }

        table.saldo-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.saldo-table th,
        table.saldo-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
            font-size: 11pt;
        }

        table.saldo-table th {
            background-color: #e8e8e8;
            font-weight: bold;
        }

        .signature-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 5px 10px;
        }

        .signature-role {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 60px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-nip {
            font-size: 10pt;
        }

        .signature-space {
            height: 60px;
        }
    </style>
</head>
<body>

{{-- Kop Surat --}}
<div class="header">
    <div class="header-instansi">Pemerintah Kabupaten Penajam Paser Utara</div>
    <div style="font-size: 11pt;">
        {{ $p->pegawai?->unitKerja?->nama ?? '-' }}
    </div>
    <div class="header-title">Formulir Pengajuan Cuti</div>
    <div class="header-nomor">Nomor: {{ $p->nomor_pengajuan }}</div>
</div>

{{-- Bagian 1: Data Pegawai --}}
<div class="section-title">I. Data Pegawai</div>
<table class="data-table">
    <tr>
        <td class="label">NIP</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->pegawai?->nip ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Nama</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->pegawai?->nama_lengkap ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Jabatan</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->pegawai?->jabatan?->nama ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Unit Kerja</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->pegawai?->unitKerja?->nama ?? '-' }}</td>
    </tr>
</table>

{{-- Bagian 2: Detail Cuti --}}
<div class="section-title">II. Detail Cuti</div>
<table class="data-table">
    <tr>
        <td class="label">Jenis Cuti</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->jenisCuti?->nama ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tanggal Mulai</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->tanggal_mulai?->translatedFormat('d F Y') ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tanggal Selesai</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->tanggal_selesai?->translatedFormat('d F Y') ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Jumlah Hari Kerja</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->jumlah_hari_kerja }} hari</td>
    </tr>
    <tr>
        <td class="label">Alasan</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->alasan ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Alamat Selama Cuti</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->alamat_selama_cuti ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">No. Telepon</td>
        <td class="separator">:</td>
        <td class="value">{{ $p->nomor_telp_selama_cuti ?? '-' }}</td>
    </tr>
</table>

{{-- Bagian 3: Riwayat Saldo (hanya untuk Cuti Tahunan) --}}
@if($p->jenis_cuti_kode === 'CT' && $p->saldoLedger->isNotEmpty())
    <div class="section-title">III. Riwayat Saldo Cuti Tahunan</div>
    <table class="saldo-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis Transaksi</th>
                <th>Jumlah Hari</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($p->saldoLedger as $index => $ledger)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $ledger->jenis_transaksi)) }}</td>
                    <td>{{ $ledger->jumlah_hari }}</td>
                    <td>{{ $ledger->keterangan ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Tanda Tangan --}}
<table class="signature-table">
    <tr>
        <td>
            <div class="signature-role">Pegawai Pemohon</div>
            <div class="signature-space"></div>
            <div class="signature-name">{{ $p->pegawai?->nama_lengkap ?? '........................' }}</div>
            <div class="signature-nip">NIP. {{ $p->pegawai?->nip ?? '........................' }}</div>
            <div style="font-size: 10pt; margin-top: 5px;">
                Tanggal: {{ $p->submitted_at?->translatedFormat('d F Y') ?? '..............................' }}
            </div>
        </td>
        <td>
            <div class="signature-role">Atasan Langsung</div>
            <div class="signature-space"></div>
            <div class="signature-name">{{ $p->atasanLangsungCurrent?->nama_lengkap ?? '........................' }}</div>
            <div class="signature-nip">NIP. {{ $p->atasanLangsungCurrent?->nip ?? '........................' }}</div>
            <div style="font-size: 10pt; margin-top: 5px;">
                Tanggal: ..............................
            </div>
        </td>
        <td>
            <div class="signature-role">Pejabat Berwenang</div>
            <div class="signature-space"></div>
            <div class="signature-name">{{ $p->pejabatBerwenangCurrent?->nama_lengkap ?? '........................' }}</div>
            <div class="signature-nip">NIP. {{ $p->pejabatBerwenangCurrent?->nip ?? '........................' }}</div>
            <div style="font-size: 10pt; margin-top: 5px;">
                Tanggal: {{ $p->approved_at?->translatedFormat('d F Y') ?? '..............................' }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
