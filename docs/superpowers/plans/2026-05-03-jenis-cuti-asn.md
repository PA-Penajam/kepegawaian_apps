# Penambahan Jenis Cuti ASN — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan 3 jenis cuti ASN (Cuti Besar, Cuti Melahirkan, CLTN) sesuai PP 11/2017, memperbaiki durasi CAP, dan menambahkan business rules validation.

**Architecture:** Menggunakan pola Strategy Pattern yang sudah ada — setiap jenis cuti punya file Rule sendiri yang implement `CutiRule` interface. Rule di-register di `AppServiceProvider`. Seeder diupdate untuk data master dan mapping per status pegawai.

**Tech Stack:** Laravel, PHP, Eloquent ORM, Spatie ModelStates

**Spec:** `docs/superpowers/specs/2026-05-03-jenis-cuti-asn-design.md`

---

## File Structure

### Files yang Dibuat (3 file baru)

| File | Tanggung Jawab |
|------|---------------|
| `app/Services/Cuti/Rules/CutiBesarRule.php` | Validasi aturan Cuti Besar (CB): masa kerja ≥5 tahun, mutual exclusion dengan CT, durasi tetap 90 hari |
| `app/Services/Cuti/Rules/CutiMelahirkanRule.php` | Validasi aturan Cuti Melahirkan (CM): gender wanita, anak ke-4+ harus CB |
| `app/Services/Cuti/Rules/CutiLtnRule.php` | Validasi aturan CLTN: masa kerja ≥5 tahun, lampiran wajib |

### Files yang Dimodifikasi (3 file)

| File | Perubahan |
|------|-----------|
| `app/Providers/AppServiceProvider.php` | Register 3 rule baru ke CutiRuleEngine |
| `database/seeders/CutiJenisMasterSeeder.php` | Tambah 3 record (CB, CM, CLTN), fix CAP durasi 60→30 |
| `database/seeders/CutiJenisPerStatusPegawaiSeeder.php` | Tambah 6 mapping baru (3 jenis × 2 status) |

---

### Task 1: Tambah Data Master — CutiJenisMasterSeeder

**Files:**
- Modify: `database/seeders/CutiJenisMasterSeeder.php`

- [ ] **Step 1: Tambah 3 record baru dan perbaiki CAP**

Edit `database/seeders/CutiJenisMasterSeeder.php`. Tambahkan 3 record baru setelah CAP, dan ubah `durasi_max_kalender` CAP dari 60 menjadi 30:

```php
$jenisCuti = [
    // ... existing CT, CS_TIER1, CS_TIER2 ...
    [
        'kode' => 'CAP',
        'nama' => 'Cuti Alasan Penting',
        'saldo_driven' => false,
        'hak_default_per_tahun' => null,
        'durasi_min_kalender' => 1,
        'durasi_max_kalender' => 30, // ← diperbaiki dari 60
        'butuh_lampiran' => true,
        'boleh_dicabut_setelah_disetujui' => true,
        'aktif' => true,
    ],
    [
        'kode' => 'CB',
        'nama' => 'Cuti Besar',
        'saldo_driven' => false,
        'hak_default_per_tahun' => null,
        'durasi_min_kalender' => 90,
        'durasi_max_kalender' => 90,
        'butuh_lampiran' => false,
        'boleh_dicabut_setelah_disetujui' => true,
        'aktif' => true,
    ],
    [
        'kode' => 'CM',
        'nama' => 'Cuti Melahirkan',
        'saldo_driven' => false,
        'hak_default_per_tahun' => null,
        'durasi_min_kalender' => 90,
        'durasi_max_kalender' => 90,
        'butuh_lampiran' => false,
        'boleh_dicabut_setelah_disetujui' => false,
        'aktif' => true,
    ],
    [
        'kode' => 'CLTN',
        'nama' => 'Cuti di Luar Tanggungan Negara',
        'saldo_driven' => false,
        'hak_default_per_tahun' => null,
        'durasi_min_kalender' => 1,
        'durasi_max_kalender' => 1095,
        'butuh_lampiran' => true,
        'boleh_dicabut_setelah_disetujui' => false,
        'aktif' => true,
    ],
];
```

- [ ] **Step 2: Jalankan seeder untuk verifikasi**

Run: `php artisan db:seed --class=Database\\Seeders\\CutiJenisMasterSeeder`

Expected: Berhasil tanpa error. 7 record di tabel `cuti_jenis_master` (4 existing + 3 baru).

- [ ] **Step 3: Verifikasi data di database**

Run: `php artisan tinker --execute="echo json_encode(App\Models\Cuti\CutiJenisMaster::all()->toArray(), JSON_PRETTY_PRINT);"`

Expected: Output menampilkan 7 record dengan kode CT, CS_TIER1, CS_TIER2, CAP, CB, CM, CLTN. CAP memiliki `durasi_max_kalender` = 30.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/CutiJenisMasterSeeder.php
git commit -m "feat(cuti): tambah 3 jenis cuti ASN (CB, CM, CLTN) dan fix durasi CAP"
```

---

### Task 2: Tambah Mapping per Status Pegawai — CutiJenisPerStatusPegawaiSeeder

**Files:**
- Modify: `database/seeders/CutiJenisPerStatusPegawaiSeeder.php`

- [ ] **Step 1: Tambah 6 mapping baru**

Edit `database/seeders/CutiJenisPerStatusPegawaiSeeder.php`. Tambahkan 6 mapping setelah CAP:

```php
$mapping = [
    // ... existing CT, CS_TIER1, CS_TIER2, CAP mappings ...
    ['jenis_cuti_kode' => 'CB', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Minimal masa kerja 5 tahun terus-menerus'],
    ['jenis_cuti_kode' => 'CB', 'status_kepegawaian' => 'PPPK', 'boleh' => false, 'hak_per_tahun' => null, 'catatan' => 'PPPK tidak berhak cuti besar sesuai PP 11/2017'],
    ['jenis_cuti_kode' => 'CM', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Khusus PNS wanita, untuk anak 1-3'],
    ['jenis_cuti_kode' => 'CM', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Khusus PPPK wanita, untuk anak 1-3'],
    ['jenis_cuti_kode' => 'CLTN', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Minimal masa kerja 5 tahun, tanpa gaji'],
    ['jenis_cuti_kode' => 'CLTN', 'status_kepegawaian' => 'PPPK', 'boleh' => false, 'hak_per_tahun' => null, 'catatan' => 'PPPK tidak berhak CLTN sesuai PP 11/2017'],
];
```

- [ ] **Step 2: Jalankan seeder untuk verifikasi**

Run: `php artisan db:seed --class=Database\\Seeders\\CutiJenisPerStatusPegawaiSeeder`

Expected: Berhasil tanpa error. 14 record di tabel `cuti_jenis_per_status_pegawai` (8 existing + 6 baru).

- [ ] **Step 3: Commit**

```bash
git add database/seeders/CutiJenisPerStatusPegawaiSeeder.php
git commit -m "feat(cuti): tambah mapping CB, CM, CLTN per status pegawai"
```

---

### Task 3: Buat CutiBesarRule

**Files:**
- Create: `app/Services/Cuti/Rules/CutiBesarRule.php`

- [ ] **Step 1: Tulis CutiBesarRule**

Buat file `app/Services/Cuti/Rules/CutiBesarRule.php`:

```php
<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use DomainException;

class CutiBesarRule implements CutiRule
{
    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CB';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateDurasi($pengajuan);
        $this->validateMasaKerja($pengajuan);
        $this->validateMutualExclusionDenganCT($pengajuan);
    }

    /**
     * Cuti Besar harus tepat 90 hari (3 bulan).
     */
    private function validateDurasi(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->jumlah_hari_kerja !== 90) {
            throw new DomainException('Cuti Besar harus tepat 90 hari (3 bulan).');
        }
    }

    /**
     * PNS harus sudah bekerja terus-menerus minimal 5 tahun.
     */
    private function validateMasaKerja(CutiPengajuan $pengajuan): void
    {
        $pegawai = $pengajuan->pegawai;
        $masaKerjaTahun = $pegawai->tanggal_pengangkatan
            ? (int) $pegawai->tanggal_pengangkatan->diffInYears(now())
            : 0;

        if ($masaKerjaTahun < 5) {
            throw new DomainException('Cuti Besar memerlukan minimal masa kerja 5 tahun terus-menerus.');
        }
    }

    /**
     * Tidak bisa mengambil Cuti Besar di tahun yang sama dengan Cuti Tahunan.
     */
    private function validateMutualExclusionDenganCT(CutiPengajuan $pengajuan): void
    {
        $sudahAmbilCT = CutiPengajuan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->whereYear('tanggal_mulai', $pengajuan->tanggal_mulai->year)
            ->whereIn('state', ['DISETUJUI', 'DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN'])
            ->where('id', '!=', $pengajuan->id)
            ->exists();

        if ($sudahAmbilCT) {
            throw new DomainException('Tidak bisa mengambil Cuti Besar di tahun yang sama dengan Cuti Tahunan.');
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax PHP**

Run: `php -l app/Services/Cuti/Rules/CutiBesarRule.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/Cuti/Rules/CutiBesarRule.php
git commit -m "feat(cuti): tambah CutiBesarRule untuk validasi CB"
```

---

### Task 4: Buat CutiMelahirkanRule

**Files:**
- Create: `app/Services/Cuti/Rules/CutiMelahirkanRule.php`

- [ ] **Step 1: Tulis CutiMelahirkanRule**

Buat file `app/Services/Cuti/Rules/CutiMelahirkanRule.php`:

```php
<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use DomainException;

class CutiMelahirkanRule implements CutiRule
{
    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CM';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateGender($pengajuan);
        $this->validateJumlahAnak($pengajuan);
    }

    /**
     * Cuti Melahirkan hanya untuk PNS/PPPK wanita.
     */
    private function validateGender(CutiPengajuan $pengajuan): void
    {
        $pegawai = $pengajuan->pegawai;

        if ($pegawai->jenis_kelamin !== 'P') {
            throw new DomainException('Cuti Melahirkan hanya untuk PNS/PPPK wanita.');
        }
    }

    /**
     * Cuti Melahirkan untuk anak 1-3. Anak ke-4+ harus menggunakan Cuti Besar.
     */
    private function validateJumlahAnak(CutiPengajuan $pengajuan): void
    {
        $jumlahMelahirkan = CutiPengajuan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CM')
            ->whereIn('state', ['DISETUJUI', 'DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN'])
            ->where('id', '!=', $pengajuan->id)
            ->count();

        if ($jumlahMelahirkan >= 3) {
            throw new DomainException('Untuk kelahiran anak ke-4 dan seterusnya, gunakan Cuti Besar.');
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax PHP**

Run: `php -l app/Services/Cuti/Rules/CutiMelahirkanRule.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/Cuti/Rules/CutiMelahirkanRule.php
git commit -m "feat(cuti): tambah CutiMelahirkanRule untuk validasi CM"
```

---

### Task 5: Buat CutiLtnRule

**Files:**
- Create: `app/Services/Cuti/Rules/CutiLtnRule.php`

- [ ] **Step 1: Tulis CutiLtnRule**

Buat file `app/Services/Cuti/Rules/CutiLtnRule.php`:

```php
<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use DomainException;

class CutiLtnRule implements CutiRule
{
    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CLTN';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateMasaKerja($pengajuan);
        $this->validateLampiran($pengajuan);
    }

    /**
     * PNS harus sudah bekerja terus-menerus minimal 5 tahun.
     */
    private function validateMasaKerja(CutiPengajuan $pengajuan): void
    {
        $pegawai = $pengajuan->pegawai;
        $masaKerjaTahun = $pegawai->tanggal_pengangkatan
            ? (int) $pegawai->tanggal_pengangkatan->diffInYears(now())
            : 0;

        if ($masaKerjaTahun < 5) {
            throw new DomainException('Cuti di Luar Tanggungan Negara memerlukan minimal masa kerja 5 tahun terus-menerus.');
        }
    }

    /**
     * CLTN wajib melampirkan dokumen pendukung.
     */
    private function validateLampiran(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->lampiran()->count() === 0) {
            throw new DomainException('Cuti di Luar Tanggungan Negara memerlukan lampiran dokumen pendukung.');
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax PHP**

Run: `php -l app/Services/Cuti/Rules/CutiLtnRule.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/Cuti/Rules/CutiLtnRule.php
git commit -m "feat(cuti): tambah CutiLtnRule untuk validasi CLTN"
```

---

### Task 6: Register Rules di AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Tambah import untuk 3 rule baru**

Tambahkan 3 import di bagian atas file `app/Providers/AppServiceProvider.php`:

```php
use App\Services\Cuti\Rules\CutiBesarRule;
use App\Services\Cuti\Rules\CutiLtnRule;
use App\Services\Cuti\Rules\CutiMelahirkanRule;
```

- [ ] **Step 2: Register 3 rule baru ke CutiRuleEngine**

Edit method `register()` di `app/Providers/AppServiceProvider.php`. Tambahkan 3 rule baru setelah `CutiAlasanPentingRule`:

```php
$this->app->bind(CutiRuleEngine::class, fn () => new CutiRuleEngine([
    app(CutiTahunanRule::class),
    app(CutiSakitTier1Rule::class),
    app(CutiSakitTier2Rule::class),
    app(CutiAlasanPentingRule::class),
    app(CutiBesarRule::class),
    app(CutiMelahirkanRule::class),
    app(CutiLtnRule::class),
]));
```

- [ ] **Step 3: Verifikasi syntax PHP**

Run: `php -l app/Providers/AppServiceProvider.php`

Expected: `No syntax errors detected`

- [ ] **Step 4: Verifikasi aplikasi bisa boot**

Run: `php artisan route:list --path=cuti 2>&1 | head -5`

Expected: Berhasil menampilkan route cuti tanpa error binding.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat(cuti): register CutiBesarRule, CutiMelahirkanRule, CutiLtnRule"
```

---

### Task 7: Verifikasi End-to-End

- [ ] **Step 1: Jalankan full seeder**

Run: `php artisan migrate:fresh --seed`

Expected: Berhasil tanpa error. Semua seeder jalan termasuk seeder cuti yang sudah diupdate.

- [ ] **Step 2: Verifikasi data cuti_jenis_master**

Run: `php artisan tinker --execute="echo App\Models\Cuti\CutiJenisMaster::count() . ' jenis cuti';"`

Expected: `7 jenis cuti`

- [ ] **Step 3: Verifikasi data cuti_jenis_per_status_pegawai**

Run: `php artisan tinker --execute="echo App\Models\Cuti\CutiJenisPerStatusPegawai::count() . ' mapping';"`

Expected: `14 mapping`

- [ ] **Step 4: Verifikasi CAP durasi sudah diperbaiki**

Run: `php artisan tinker --execute="echo App\Models\Cuti\CutiJenisMaster::find('CAP')->durasi_max_kalender;"`

Expected: `30`

- [ ] **Step 5: Commit final**

```bash
git add -A
git commit -m "feat(cuti): selesai tambah 3 jenis cuti ASN sesuai PP 11/2017"
```

---

## Ringkasan Perubahan

| Task | File | Aksi |
|------|------|------|
| 1 | `CutiJenisMasterSeeder.php` | +3 record, fix CAP durasi |
| 2 | `CutiJenisPerStatusPegawaiSeeder.php` | +6 mapping |
| 3 | `CutiBesarRule.php` | Buat baru |
| 4 | `CutiMelahirkanRule.php` | Buat baru |
| 5 | `CutiLtnRule.php` | Buat baru |
| 6 | `AppServiceProvider.php` | Register 3 rule |
| 7 | End-to-end verification | Jalankan seeder + verifikasi |
