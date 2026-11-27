<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Izin Cuti - {{ $nomorSurat }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Serif', Georgia, serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            padding: 25px 35px;
        }
        .kop {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .kop-instansi {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .kop-alamat {
            font-size: 9.5pt;
            margin-top: 2px;
        }
        .judul {
            text-align: center;
            margin-bottom: 16px;
        }
        .judul h1 {
            font-size: 12pt;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }
        .judul .nomor {
            font-size: 10.5pt;
            margin-top: 3px;
        }
        .paragraf {
            margin-bottom: 12px;
            text-align: justify;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 14px 20px;
        }
        table.detail td {
            padding: 3px 4px;
            vertical-align: top;
        }
        table.detail td.label {
            width: 30%;
        }
        table.detail td.sep {
            width: 3%;
        }
        .ttd-wrap {
            margin-top: 28px;
            width: 100%;
        }
        .ttd-box {
            width: 48%;
            margin-left: 52%;
            text-align: left;
        }
        .ttd-space {
            height: 64px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="kop">
        <div class="kop-instansi">{{ $namaSatker }}</div>
        <div class="kop-alamat">{{ $alamatSatker }}</div>
    </div>

    <div class="judul">
        <h1>SURAT IZIN {{ strtoupper($pengajuan->jenisCuti?->nama ?? 'CUTI') }}</h1>
        <div class="nomor">Nomor: {{ $nomorSurat }}</div>
    </div>

    <p class="paragraf">
        Diberikan {{ strtolower($pengajuan->jenisCuti?->nama ?? 'cuti') }} kepada Pegawai Negeri Sipil / Aparatur Pengadilan Agama Penajam:
    </p>

    <table class="detail">
        <tr>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td>{{ $pengajuan->pegawai?->nama_lengkap ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NIP</td>
            <td class="sep">:</td>
            <td>{{ $pengajuan->pegawai?->nip ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pangkat / Golongan</td>
            <td class="sep">:</td>
            <td>{{ $pengajuan->pegawai?->pangkat?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan</td>
            <td class="sep">:</td>
            <td>{{ $pengajuan->pegawai?->jabatan?->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Unit Kerja</td>
            <td class="sep">:</td>
            <td>{{ $pengajuan->pegawai?->unitKerja?->nama ?? '-' }}</td>
        </tr>
    </table>

    <p class="paragraf">
        Terhitung mulai tanggal <strong>{{ $pengajuan->tanggal_mulai?->translatedFormat('d F Y') }}</strong>
        sampai dengan tanggal <strong>{{ $pengajuan->tanggal_selesai?->translatedFormat('d F Y') }}</strong>
        selama <strong>{{ $pengajuan->jumlah_hari_kerja }}</strong> hari kerja, dengan ketentuan sebagai berikut:
    </p>

    <p class="paragraf">
        1. Sebelum menjalankan cuti wajib menyerahkan pekerjaan kepada atasan langsung atau pejabat lain yang ditunjuk.<br>
        2. Setelah selesai menjalankan cuti wajib melaporkan diri kepada atasan langsung dan bekerja kembali sebagaimana mestinya.<br>
        3. Nomor pengajuan internal: {{ $pengajuan->nomor_pengajuan }}.
    </p>

    <div class="ttd-wrap">
        <div class="ttd-box">
            <div>Penajam, {{ $tanggalSurat->translatedFormat('d F Y') }}</div>
            <div>{{ $jabatanPenandatangan }}</div>
            <div class="ttd-space"></div>
            <div class="ttd-nama">{{ $pejabatPenandatangan?->nama_lengkap ?? '.................................' }}</div>
            @if ($pejabatPenandatangan?->nip)
                <div>NIP. {{ $pejabatPenandatangan->nip }}</div>
            @endif
        </div>
    </div>
</body>
</html>
