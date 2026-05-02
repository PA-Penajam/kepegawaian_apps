# Sistem Error & Success Handling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan feedback error & success yang konsisten ke SEMUA modul di aplikasi kepegawaian agar user selalu mendapatkan konfirmasi apakah operasi yang mereka lakukan berhasil atau gagal.

**Architecture:**
1. Backend: Menambahkan `->with()` flash message di SEMUA operasi store/update/delete
2. Backend: Exception handler yang user-friendly untuk semua exception umum
3. Frontend: Loading state pada button form untuk mencegah double submit
4. Frontend: Pesan error yang human-readable dan konsisten

**Tech Stack:** Laravel 12, Inertia.js v2, React 19, Tailwind CSS v4

---

## Task 1: Tambahkan Flash Message ke Semua Operasi Cuti

**Files:**
- Modify: `app/Http/Controllers/Cuti/PengajuanController.php`
- Modify: `app/Http/Controllers/Cuti/ApprovalController.php`

- [ ] Tambahkan `->with('success', ...)` pada operasi store, update, destroy
- [ ] Tambahkan error handling dengan try-catch dan `->with('error', ...)`
- [ ] Pastikan pesan dalam Bahasa Indonesia yang jelas
- [ ] Run tests untuk modul Cuti
- [ ] Commit perubahan

---

## Task 2: Tambahkan Flash Message ke Semua Operasi Kepegawaian

**Files:**
- Modify: `app/Http/Controllers/Kepegawaian/PegawaiController.php`
- Modify: `app/Http/Controllers/Kepegawaian/RiwayatPangkatController.php`
- Modify: `app/Http/Controllers/Kepegawaian/RiwayatJabatanController.php`
- Modify: `app/Http/Controllers/Kepegawaian/RiwayatPendidikanController.php`
- Modify: `app/Http/Controllers/Kepegawaian/KeluargaController.php`

- [ ] Tambahkan `->with('success', ...)` pada operasi store, update, destroy
- [ ] Tambahkan error handling dengan try-catch
- [ ] Pastikan semua operasi menghapus, mengupdate, menambah data memberikan feedback
- [ ] Run tests untuk modul Kepegawaian
- [ ] Commit perubahan

---

## Task 3: Tambahkan Flash Message ke Semua Operasi IAM & Settings

**Files:**
- Modify: `app/Http/Controllers/Iam/RoleController.php`
- Modify: `app/Http/Controllers/Iam/PermissionController.php`
- Modify: `app/Http/Controllers/Settings/ProfileController.php`
- Modify: `app/Http/Controllers/Settings/SecurityController.php`

- [ ] Tambahkan `->with('success', ...)` pada semua operasi
- [ ] Tambahkan error handling
- [ ] Run tests untuk modul IAM
- [ ] Commit perubahan

---

## Task 4: Implementasi Global Exception Handler User-Friendly

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `app/Exceptions/Handler.php`

- [ ] Tambahkan exception handler global untuk semua jenis exception umum
- [ ] Redirect kembali ke halaman sebelumnya dengan flash error
- [ ] Pastikan pesan error mudah dipahami oleh user (bukan stack trace)
- [ ] Tetap log exception asli ke file log untuk debugging
- [ ] Commit perubahan

---

## Task 5: Tambahkan Loading State pada Semua Button Form

**Files:**
- Modify: `resources/js/components/kepegawaian/crud-form-card.tsx`
- Modify: `resources/js/components/ui/button.tsx`
- Modify: Semua halaman form di `resources/js/pages/`

- [ ] Tambahkan properti `processing` pada Button component
- [ ] Tampilkan spinner ketika form sedang di-submit
- [ ] Disable button saat processing untuk mencegah double submit
- [ ] Terapkan ke SEMUA form yang ada
- [ ] Commit perubahan

---

## Task 6: Perbaiki Konsistensi Flash Message Styling

**Files:**
- Modify: `resources/js/components/flash-messages.tsx`

- [ ] Tambahkan styling untuk pesan warning dan info
- [ ] Sesuaikan durasi tampil pesan agar cukup lama untuk dibaca
- [ ] Pastikan animasi smooth dan tidak mengganggu
- [ ] Tambahkan keyboard shortcut untuk menutup pesan
- [ ] Commit perubahan

---

## Task 7: Tambahkan Error Display pada Setiap Halaman Form

**Files:**
- Semua halaman form di `resources/js/pages/`

- [ ] Tambahkan AlertError di atas setiap form
- [ ] Tampilkan error validation dari backend dengan jelas
- [ ] Pastikan error field di-highlight dengan benar
- [ ] Tambahkan pesan error untuk network error
- [ ] Commit perubahan

---

## Task 8: Verifikasi & Testing Akhir

**Files:** Semua file yang dimodifikasi

- [ ] Jalankan seluruh test suite
- [ ] Test manual setiap operasi CRUD di semua modul
- [ ] Pastikan tidak ada exception yang keluar mentah ke user
- [ ] Pastikan setiap operasi selalu memberikan feedback kepada user
- [ ] Jalankan `vendor/bin/pint` untuk memperbaiki formatting kode
- [ ] Commit final perubahan
