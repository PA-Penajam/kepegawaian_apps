# Perbaikan Bug dan Placeholder — Design

**Tanggal:** 2026-04-20  
**Status:** Approved  
**Scope:** Memperbaiki bug dan placeholder yang teridentifikasi di codebase  
**Pendekatan:** Single Dynamic Form untuk self-service, DRY fix untuk seeder, data enrichment untuk export, theme-aware styling untuk chart

---

## Ringkasan Bug

| # | Bug | Lokasi | Dampak |
|---|-----|--------|--------|
| 1 | Form create self-service berupa placeholder | `resources/js/pages/self-service/pengajuan/create.tsx` | Fitur self-service tidak bisa digunakan oleh pegawai |
| 2 | IamSeeder gagal saat di-run ulang | `database/seeders/IamSeeder.php` | Developer tidak bisa re-seed database |
| 3 | Export KGB kolom "Unit Kerja" kosong | `app/Exports/KgbMonitoringExport.php` | Data export tidak lengkap |
| 4 | Chart tooltip dan fill warna hardcoded | Dashboard chart components | Tidak konsisten dengan tema aplikasi |

---

## Keputusan Desain

### Bug 1: Form Self-Service Create (Pendekatan 1 — Single Dynamic Form)

**Alasan:** Mengikuti filosofi design doc fase 5.2 yang menekankan simplicity dan maintainability. Satu halaman create dengan state-driven form cukup untuk semua domain.

**Komponen form:**
- **Step 1 — Pilih Domain:** Dropdown domain (`profil_pribadi`, `pasangan`, `anak`, `orang_tua`, `keluarga_lain`)
- **Step 2 — Pilih Aksi:** Dropdown aksi (`update`, `create`, `delete`) — untuk `profil_pribadi` hanya `update`
- **Step 3 — Isi Field:** Field muncul secara dinamis berdasarkan domain
- **Step 4 — Upload Lampiran:** Muncul otomatis saat diperlukan (berdasarkan aturan validasi backend)

**Field per domain:**
- `profil_pribadi`: nama_lengkap, nik, tempat_lahir, tanggal_lahir, status_perkawinan, alamat, no_telepon, email
- `pasangan` / `anak` / `orang_tua` / `keluarga_lain`: hubungan, nama, tempat_lahir, tanggal_lahir, jenis_kelamin, pekerjaan, pendidikan, keterangan

**Aturan lampiran:**
- Wajib untuk: `pasangan`, `anak`, `keluarga_lain` (semua aksi)
- Wajib untuk `profil_pribadi` jika field yang berubah termasuk: nama_lengkap, nik, tempat_lahir, tanggal_lahir, status_perkawinan

**Validasi:** Mengandalkan Laravel FormRequest (`StorePengajuanPerubahanDataRequest`) — frontend hanya menampilkan error yang dikembalikan.

### Bug 2: IamSeeder Duplicate Key

**Masalah:** `new IamApplication([...])->save()` selalu membuat record baru, menyebabkan duplicate `slug` error saat re-seed.

**Fix:** Gunakan `firstOrCreate` dengan `slug` sebagai unique key.

### Bug 3: Export KGB Unit Kerja Kosong

**Masalah:** `collection()` memanggil `getUpcomingKgb()` tapi hanya mem-map field yang ada di return value service. `unit_kerja` tidak di-include.

**Fix:** Pastikan `collection()` mengambil `unit_kerja` dari hasil service dan memasukkannya ke object. Jika service tidak mengembalikan unit_kerja, perlu diperiksa di service layer.

### Bug 4: Chart Warna Hardcoded

**Masalah:** Warna chart (`#6366f1`, `#f472b6`) dan tooltip background tidak mengikuti tema Tailwind CSS aplikasi.

**Fix:** Gunakan CSS variable Tailwind atau theme colors dari design system. Tooltip menggunakan `bg-popover` dan `text-popover-foreground` dari shadcn/ui theme.

---

## Acceptance Criteria

- [ ] Pegawai bisa mengajukan perubahan profil pribadi melalui form yang lengkap
- [ ] Pegawai bisa mengajukan perubahan data keluarga melalui form yang lengkap
- [ ] Lampiran wajib muncul dan divalidasi sesuai aturan domain
- [ ] `php artisan db:seed --class=IamSeeder` bisa dijalankan berkali-kali tanpa error
- [ ] Export Excel KGB menampilkan kolom Unit Kerja dengan benar
- [ ] Chart dashboard menggunakan warna yang konsisten dengan tema aplikasi
- [ ] Semua test (379) tetap passing
