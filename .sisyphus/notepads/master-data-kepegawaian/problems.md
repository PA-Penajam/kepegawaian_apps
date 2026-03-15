# Problems — master-data-kepegawaian

## [2026-03-15] Initialized

- Belum ada blockers

## [2026-03-15] F4 Scope Fidelity

- Deviasi scope yang belum ditutup:
    - T1: nilai enum tambahan di `HubunganKeluarga` (creep).
    - T15: path route dokumen tidak sesuai expected QA spec.

## [2026-03-15] F3 Manual QA

- Seed data akun QA belum sinkron dengan acceptance criteria final QA; tanpa akun admin dan viewer yang diminta, verifikasi end-to-end utama terblokir.
- Route `/kepegawaian/pegawai/create` masih berpotensi gagal 500 karena dependensi kolom `urutan` pada `ref_jabatan`.

## 2026-03-15 F3 QA

- Tambahkan persona seed `admin` dan viewer yang sudah terhubung ke `pegawai_id` agar skenario manual QA final bisa dieksekusi end-to-end setelah `migrate:fresh --seed`.
- Perbaiki query referensi jabatan pada create form pegawai yang masih mengurutkan kolom `urutan` yang tidak ada di tabel `ref_jabatan`.

## [2026-03-15] F3 QA Final Verification Wave

- Tambahkan fallback avatar/default image yang valid untuk self-service agar request `/pegawai/default-2.jpg` tidak lagi menghasilkan `404` di browser console.
- Sinkronkan seed persona QA dengan acceptance manual QA: minimal satu admin dan satu viewer yang sudah linked ke `pegawai_id` perlu tersedia setelah `migrate:fresh --seed`.

## [2026-03-15] F3 Manual QA (correction)

- Blocker final QA yang benar-benar tervalidasi adalah kegagalan halaman create pegawai (`/kepegawaian/pegawai/create`) dengan SQL error `Unknown column urutan in ORDER BY`.
- Metadata brief QA tidak sinkron dengan seed nyata (`.test` vs `.example.com`, dan viewer linked person berbeda), sehingga instruksi QA perlu diperbarui agar sesuai environment.
