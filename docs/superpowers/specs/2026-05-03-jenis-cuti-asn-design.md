# Spesifikasi Desain: Penambahan Jenis Cuti ASN

**Tanggal:** 2026-05-03
**Status:** Approved
**Dasar Hukum:** PP Nomor 11 Tahun 2017 tentang Manajemen PNS jo Peraturan BKN No. 7 Tahun 2021

---

## 1. Ringkasan

Menambahkan 3 jenis cuti ASN yang belum ada di sistem sesuai PP 11/2017, serta memperbaiki durasi Cuti Alasan Penting (CAP) dari 60 hari menjadi 30 hari sesuai regulasi.

### Jenis Cuti yang Ditambahkan

| # | Kode | Nama | Dasar Hukum |
|---|------|------|-------------|
| 1 | `CB` | Cuti Besar | PP 11/2017 Pasal 322-324 |
| 2 | `CM` | Cuti Melahirkan | PP 11/2017 Pasal 326-328 |
| 3 | `CLTN` | Cuti di Luar Tanggungan Negara | PP 11/2017 Pasal 329-331 |

### Jenis Cuti yang Sudah Ada

| Kode | Nama | Perubahan |
|------|------|-----------|
| `CT` | Cuti Tahunan | Tidak ada perubahan |
| `CS_TIER1` | Cuti Sakit (1-14 hari) | Tidak ada perubahan |
| `CS_TIER2` | Cuti Sakit (>14 hari) | Tidak ada perubahan |
| `CAP` | Cuti Alasan Penting | **Perbaikan durasi max: 60 → 30 hari** |

### Cuti Bersama

Tidak perlu ditambahkan sebagai jenis cuti di `cuti_jenis_master`. Cuti Bersama sudah ditangani melalui tabel `cuti_libur_master` dengan flag `is_cuti_bersama = true`, dianggap sebagai hari libur nasional dan tidak memotong cuti tahunan.

---

## 2. Data Model

### 2.1 CutiJenisMaster — Record Baru

```php
// CB - Cuti Besar
[
    'kode' => 'CB',
    'nama' => 'Cuti Besar',
    'saldo_driven' => false,
    'hak_default_per_tahun' => null,
    'durasi_min_kalender' => 90,    // 3 bulan
    'durasi_max_kalender' => 90,    // 3 bulan
    'butuh_lampiran' => false,
    'boleh_dicabut_setelah_disetujui' => true,
    'aktif' => true,
]

// CM - Cuti Melahirkan
[
    'kode' => 'CM',
    'nama' => 'Cuti Melahirkan',
    'saldo_driven' => false,
    'hak_default_per_tahun' => null,
    'durasi_min_kalender' => 90,    // 3 bulan (1 bulan sebelum + 2 bulan sesudah)
    'durasi_max_kalender' => 90,    // 3 bulan
    'butuh_lampiran' => false,
    'boleh_dicabut_setelah_disetujui' => false,
    'aktif' => true,
]

// CLTN - Cuti di Luar Tanggungan Negara
[
    'kode' => 'CLTN',
    'nama' => 'Cuti di Luar Tanggungan Negara',
    'saldo_driven' => false,
    'hak_default_per_tahun' => null,
    'durasi_min_kalender' => 1,
    'durasi_max_kalender' => 1095,  // 3 tahun
    'butuh_lampiran' => true,       // Wajib lampiran dokumen pendukung
    'boleh_dicabut_setelah_disetujui' => false,
    'aktif' => true,
]
```

### 2.2 Perbaikan CAP

```php
// Sebelum
'durasi_max_kalender' => 60,    // 2 bulan

// Sesudah
'durasi_max_kalender' => 30,    // 1 bulan (sesuai PP 11/2017 Pasal 325 ayat 4)
```

### 2.3 CutiJenisPerStatusPegawai — Mapping Baru

| Jenis Cuti | Status Kepegawaian | Boleh | Hak Per Tahun | Catatan |
|-----------|-------------------|:---:|:---:|---------|
| CB | PNS | true | null | Cuti besar setelah 5 tahun kerja terus-menerus |
| CB | PPPK | false | null | PPPK tidak berhak cuti besar sesuai regulasi |
| CM | PNS | true | null | Khusus PNS wanita (validasi gender di business logic) |
| CM | PPPK | true | null | PPPK wanita juga berhak cuti melahirkan |
| CLTN | PNS | true | null | Cuti di luar tanggungan negara |
| CLTN | PPPK | false | null | PPPK tidak berhak CLTN sesuai regulasi |

---

## 3. Business Rules Validation

### 3.1 Aturan Khusus per Jenis Cuti

#### Cuti Besar (CB)

1. **Masa kerja ≥5 tahun**: PNS harus sudah bekerja terus-menerus minimal 5 tahun
2. **Mutual exclusion dengan CT**: Tidak bisa mengambil Cuti Besar di tahun yang sama dengan Cuti Tahunan yang sudah digunakan/disetujui
3. **Durasi tetap**: Tepat 90 hari (3 bulan), tidak bisa kurang atau lebih
4. **Hak Cuti Tahunan**: PNS yang menggunakan Cuti Besar tidak berhak atas Cuti Tahunan dalam tahun yang bersangkutan

#### Cuti Melahirkan (CM)

1. **Gender validation**: Hanya untuk PNS/PPPK wanita (`jenis_kelamin = 'P'`)
2. **Anak 1-3**: Cuti Melahirkan untuk kelahiran anak pertama sampai ketiga
3. **Anak ke-4+**: Untuk kelahiran anak keempat dan seterusnya, menggunakan Cuti Besar (CB)
4. **Durasi**: 1 bulan sebelum dan 2 bulan sesudah persalinan (total 90 hari)
5. **Gugur kandungan**: PNS yang mengalami gugur kandungan berhak cuti sakit paling lama 1,5 bulan (menggunakan CS_TIER1/CS_TIER2)

#### Cuti di Luar Tanggungan Negara (CLTN)

1. **Masa kerja ≥5 tahun**: PNS harus sudah bekerja terus-menerus minimal 5 tahun
2. **Tanpa gaji**: Selama CLTN, PNS tidak berhak menerima penghasilan
3. **Tidak dihitung masa kerja**: Periode CLTN tidak diperhitungkan sebagai masa kerja
4. **Diberhentikan dari jabatan**: PNS diberhentikan dari jabatannya selama CLTN
5. **Wajib lapor**: Setelah selesai CLTN, PNS wajib melaporkan diri ke instansi induk paling lama 1 bulan
6. **Perpanjangan**: Dapat diperpanjang paling lama 1 tahun untuk alasan penting

### 3.2 Lokasi Implementasi

Validasi ditambahkan di service layer yang menangani pembuatan pengajuan cuti. Urutan validasi dari yang paling umum ke paling spesifik:

```php
function validateNewPengajuan(Pegawai $pegawai, CutiJenisMaster $jenisCuti, int $jumlahHari): void
{
    // 1. Validasi eligibility berdasarkan status pegawai (PNS/PPPK)
    $mapping = CutiJenisPerStatusPegawai::where('jenis_cuti_kode', $jenisCuti->kode)
        ->where('status_kepegawaian', $pegawai->status_kepegawaian)
        ->first();

    if (!$mapping || !$mapping->boleh) {
        throw new ValidationException(
            "{$pegawai->status_kepegawaian} tidak berhak mengajukan {$jenisCuti->nama}"
        );
    }

    // 2. Validasi gender untuk Cuti Melahirkan
    if ($jenisCuti->kode === 'CM' && $pegawai->jenis_kelamin !== 'P') {
        throw new ValidationException('Cuti Melahirkan hanya untuk PNS/PPPK wanita');
    }

    // 3. Validasi masa kerja untuk CB dan CLTN
    if (in_array($jenisCuti->kode, ['CB', 'CLTN'])) {
        $masaKerja = $pegawai->hitungMasaKerja();
        if ($masaKerja < 5) {
            throw new ValidationException('Minimal masa kerja 5 tahun untuk cuti ini');
        }
    }

    // 4. Validasi durasi tetap untuk CB (tepat 90 hari)
    if ($jenisCuti->kode === 'CB' && $jumlahHari !== 90) {
        throw new ValidationException('Cuti Besar harus tepat 90 hari (3 bulan)');
    }

    // 5. Validasi mutual exclusion CB vs CT di tahun yang sama
    if ($jenisCuti->kode === 'CB') {
        $sudahAmbilCT = CutiPengajuan::where('pegawai_nip', $pegawai->nip)
            ->where('jenis_cuti_kode', 'CT')
            ->whereYear('tanggal_mulai', now()->year)
            ->whereIn('state', ['DISETUJUI', 'DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN'])
            ->exists();
        if ($sudahAmbilCT) {
            throw new ValidationException('Tidak bisa mengambil Cuti Besar di tahun yang sama dengan Cuti Tahunan');
        }
    }

    // 6. Validasi anak ke-4+ untuk Cuti Melahirkan
    if ($jenisCuti->kode === 'CM') {
        $jumlahMelahirkan = CutiPengajuan::where('pegawai_nip', $pegawai->nip)
            ->where('jenis_cuti_kode', 'CM')
            ->whereIn('state', ['DISETUJUI', 'DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN'])
            ->count();
        if ($jumlahMelahirkan >= 3) {
            throw new ValidationException('Untuk anak ke-4 dan seterusnya, gunakan Cuti Besar');
        }
    }
}
```

**Catatan**: Validasi #1 (eligibility PPPK) juga berlaku secara tidak langsung melalui `cuti_jenis_per_status_pegawai` — namun karena form pengajuan hanya menampilkan jenis cuti yang `boleh = true` untuk status pegawai tersebut, validasi ini bersifat **defense in depth** (pengguna tidak seharusnya bisa mencapai titik ini jika status-nya tidak diizinkan).

### 3.3 CLTN Special Handling

- CLTN tidak mencatat debit di `cuti_saldo_ledger` (karena tidak ada saldo yang terpotong)
- Setelah approval CLTN, perlu event khusus di `cuti_events` yang menandai PNS "sedang cuti di luar tanggungan negara"
- Event ini akan di-consume oleh modul kepegawaian untuk update status pegawai

---

## 4. Frontend

### 4.1 Form Pengajuan Cuti (`create.tsx`)

- Dropdown jenis cuti otomatis menampilkan CB, CM, CLTN (data dinamis dari `cuti_jenis_master`)
- **Tidak ada perubahan UI** untuk menambah opsi
- Validasi backend mengembalikan error message yang jelas di frontend

### 4.2 Dashboard Saldo (`my-dashboard.tsx`)

- CB, CM, CLTN tidak menampilkan saldo (bukan saldo-driven)
- Ditampilkan sebagai "Tersedia" atau "Tidak Tersedia" berdasarkan eligibility

### 4.3 Admin: Inisialisasi Saldo (`admin-init.tsx`)

- Tidak ada perubahan (hanya CT yang perlu alokasi tahunan)

---

## 5. Dampak ke Seeder

### File yang Diubah

| File | Perubahan |
|------|-----------|
| `CutiJenisMasterSeeder.php` | Tambah 3 record (CB, CM, CLTN) + perbaiki CAP durasi |
| `CutiJenisPerStatusPegawaiSeeder.php` | Tambah 6 mapping baru (3 jenis × 2 status) |

### File yang Tidak Diubah

- Migration files (tidak perlu migration baru)
- Model files (tidak ada perubahan struktur)
- Factory files (bisa ditambahkan nanti jika diperlukan)

---

## 6. Rangkuman Perubahan

| Komponen | Perubahan |
|----------|-----------|
| `CutiJenisMasterSeeder.php` | +3 record baru, fix CAP durasi |
| `CutiJenisPerStatusPegawaiSeeder.php` | +6 mapping baru |
| Service layer (pengajuan) | +validasi business rules untuk CB, CM, CLTN |
| Frontend | Tidak ada perubahan (data dinamis) |
| Migration | Tidak ada migration baru |
