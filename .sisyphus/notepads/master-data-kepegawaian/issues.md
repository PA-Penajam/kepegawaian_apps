# Issues — master-data-kepegawaian

## [2026-03-15] Initialized

- Belum ada implementasi apapun

## [2026-03-15] Task 9: Riwayat Jabatan

- `npm` masih mengeluarkan warning `Unknown project config "public-hoist-pattern"` saat menjalankan formatter/build. Tidak memblokir `npm run build`, tetapi konfigurasi npm proyek sebaiknya dirapikan di task terpisah.

## [2026-03-15] F1 Audit: Plan Compliance

- `php artisan test --compact` lulus: 214 tests, 1075 assertions.
- `vendor/bin/pint --dirty --format agent` lulus tanpa perubahan.
- `npm run build` lulus, tetapi npm masih mengeluarkan warning `Unknown project config "public-hoist-pattern"`.
- Task 18 belum sepenuhnya sesuai plan: `app/Http/Controllers/DashboardController.php` dan `resources/js/pages/dashboard.tsx` belum menyediakan distribusi per jabatan, distribusi pendidikan terakhir, atau kartu `Pegawai Baru (bulan ini)`.
- Task 22 belum sepenuhnya sesuai plan: `app/Http/Controllers/Kepegawaian/PegawaiController.php` dan `resources/js/pages/kepegawaian/pegawai/index.tsx` belum memiliki filter `jabatan` dan sorting kolom `Jabatan`.
- Task 9 dan Task 10 menyimpang dari acceptance route pattern karena `routes/web.php` memakai `->shallow()` untuk `riwayat-jabatan` dan `riwayat-pendidikan`, sehingga route update/delete tidak lagi nested di bawah `/kepegawaian/pegawai/{pegawai}`.
- Evidence audit belum lengkap: `.sisyphus/evidence/final-qa/` kosong dan tidak ada artefak untuk beberapa task frontend akhir (mis. Task 7, 16, 17, 19, 20, 22).

## [2026-03-15] F4 Scope Fidelity

- T1 tidak 1:1 dengan spec: `HubunganKeluarga` menambahkan `AyahMertua` dan `IbuMertua` di luar daftar yang diminta (`app/Enums/HubunganKeluarga.php:12`, `resources/js/types/kepegawaian.ts:97`).
- T15 tidak 1:1 dengan expected route QA: route nested dokumen diharapkan `pegawai/{pegawai}/dokumen`, implementasi memakai `pegawai/{pegawai}/dokumen-pegawai` (`routes/web.php:66`).

## [2026-03-15] F3 Manual QA

- Kredensial QA yang diwajibkan task (`admin@pa-penajam.test` dan `viewer@pa-penajam.test`) tidak ada di database aktif; clean login keduanya gagal dengan `These credentials do not match our records.`
- Verifikasi read-only via `php artisan tinker` menunjukkan tabel `users` hanya berisi `test@example.com` dengan role `viewer`, sehingga precondition QA end-to-end tidak terpenuhi.
- Pada sesi admin lama yang sempat tersisa sebelum cookies dibersihkan, route `/kepegawaian/pegawai/create` memunculkan `500 Internal Server Error` karena query mengurutkan `ref_jabatan.urutan` yang tidak ada.

## 2026-03-15 F3 QA

- Tidak ada user `admin` yang dibuat oleh `DatabaseSeeder`, sehingga final QA admin flow tidak bisa divalidasi pada clean seed state.
- Route admin `/kepegawaian/pegawai`, detail pegawai, `/kepegawaian/monitoring/kgb`, dan `/kepegawaian/monitoring/kenaikan-pangkat` menghasilkan `403 Forbidden` untuk user seed yang tersedia.
- Halaman `/kepegawaian/pegawai/create` gagal dengan `SQLSTATE[42S22]: Unknown column 'urutan' in 'ORDER BY'` saat memuat referensi jabatan.

## [2026-03-15] F3 QA Final Verification Wave

- Halaman `/self-service` untuk viewer linked memuat data pegawai, tetapi memicu browser console error `404 Not Found` karena asset `/pegawai/default-2.jpg` tidak tersedia.
- Halaman `/kepegawaian/pegawai/create` tetap gagal render di clean state dengan `500 Internal Server Error`; query `RefJabatan::orderBy('urutan')` masih mengacu ke kolom yang tidak ada.
- `migrate:fresh --seed` hanya menyediakan `test@example.com` (viewer tanpa `pegawai_id`), sehingga persona QA admin/viewer linked harus dibuat manual lewat `tinker` untuk menjalankan final verification wave.

## [2026-03-15] F3 Manual QA (correction)

- Database aktif memang memiliki akun QA, tetapi berbeda dari brief: `admin@example.com` (admin) dan `viewer@example.com` (viewer), bukan domain `.test`.
- Satu blocker fungsional yang tervalidasi browser: route `/kepegawaian/pegawai/create` mengembalikan `500 Internal Server Error` karena query mengurutkan kolom `ref_jabatan.urutan` yang tidak ada.
- Data self-service viewer tampil normal, tetapi brief menyebut viewer ter-link ke Fattah; data seed nyata ter-link ke `Ade Ramadan`.

## [2026-03-15] F3 QA Re-run (post-fix)

- Rerun final QA terverifikasi PASS untuk seluruh 11 scenario; blocker lama Scenario 8 (`/kepegawaian/pegawai/create` 500 karena `ref_jabatan.urutan`) sudah tidak terjadi.
- Route create pegawai sekarang render normal dan data referensi termuat di payload Inertia (`refJabatan=15`, `refPangkat=17`, `refUnitKerja=8`).
- Pada state `migrate:fresh --seed`, akun QA `admin@example.com`/`viewer@example.com` tidak otomatis tersedia; untuk menjalankan skenario sesuai brief perlu penyiapan akun QA via tinker.

## [2026-03-15] F4 Scope Fidelity Audit — Run 3

- **False Positive Terklarifikasi**: String 'dokumen-pegawai' di dan adalah VALID karena:
    - Merujuk ke Inertia component name ()
    - Bukan route URL path (yang sudah diubah ke )
    - Perbedaan antara route path vs component name adalah expected behavior dalam Inertia
- Semua audit checks PASS:
    1. Out-of-scope features: PASS (gaji_pokok adalah atribut data, bukan sistem penggajian)
    2. HubunganKeluarga enum: PASS (tepat 5 values)
    3. Route path dokumen: PASS (sudah )
    4. Dependencies: PASS (sesuai Laravel Boost guidelines)
    5. Repository/service layer: PASS (2 service diizinkan untuk business logic kompleks)
    6. Chart library: PASS (tidak ada)
- Verdict: **APPROVE**

## [2026-03-15] F4 Scope Fidelity Audit — Run 3

- **False Positive Terklarifikasi**: String 'dokumen-pegawai' di `DokumenPegawaiController.php` dan `DokumenPegawaiTest.php` adalah VALID karena:
    - Merujuk ke Inertia component name (`resources/js/pages/kepegawaian/pegawai/dokumen-pegawai.tsx`)
    - Bukan route URL path (yang sudah diubah ke `/dokumen`)
    - Perbedaan antara route path vs component name adalah expected behavior dalam Inertia
- Semua audit checks PASS:
    1. Out-of-scope features: PASS (gaji_pokok adalah atribut data, bukan sistem penggajian)
    2. HubunganKeluarga enum: PASS (tepat 5 values)
    3. Route path dokumen: PASS (sudah `/dokumen`)
    4. Dependencies: PASS (sesuai Laravel Boost guidelines)
    5. Repository/service layer: PASS (2 service diizinkan untuk business logic kompleks)
    6. Chart library: PASS (tidak ada)
- Verdict: **APPROVE**
