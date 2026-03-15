# F3 QA Summary

Date: 2026-03-15

| Scenario | Description                          | Result | Notes                                                                                                                                                                                      |
| -------- | ------------------------------------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1        | Login Admin                          | PASS   | Login admin berhasil ke `/dashboard` menggunakan akun seed aktual `admin@example.com`; brief task menuliskan `admin@pa-penajam.test`, tetapi database aktif memakai domain `.example.com`. |
| 2        | Dashboard Load                       | PASS   | Dashboard memuat normal, 4 kartu statistik tampil, section distribusi tersedia, dan tidak ada error console pada load dashboard.                                                           |
| 3        | Pegawai Index Load                   | PASS   | Halaman `/kepegawaian/pegawai` memuat tabel dengan 15 baris data pada halaman pertama.                                                                                                     |
| 4        | Pegawai Search                       | PASS   | Search `Fattah` memfilter data dari 15 menjadi 1 baris.                                                                                                                                    |
| 5        | Pegawai Detail (Show)                | PASS   | Halaman detail pegawai memuat normal dengan 9 tab (Biodata, Keluarga, Riwayat Pangkat, dst).                                                                                               |
| 6        | Monitoring KGB                       | PASS   | Halaman monitoring KGB memuat normal dan tidak blank.                                                                                                                                      |
| 7        | Monitoring KP                        | PASS   | Halaman monitoring kenaikan pangkat memuat normal dan tidak blank.                                                                                                                         |
| 8        | Pegawai Create Form                  | FAIL   | Route `/kepegawaian/pegawai/create` mengembalikan `500 Internal Server Error` dengan `SQLSTATE[42S22]: Unknown column 'urutan' in 'ORDER BY'` saat memuat referensi `ref_jabatan`.         |
| 9        | Admin RBAC Access                    | PASS   | Admin dapat mengakses `/kepegawaian/pegawai` dan `/kepegawaian/monitoring/kgb` dengan status 200.                                                                                          |
| 10       | Logout & Login Viewer (Self-Service) | PASS   | Self-service viewer memuat normal menggunakan akun seed aktual `viewer@example.com`; brief task menyebut viewer linked ke Fattah, tetapi data seed aktif ter-link ke `Ade Ramadan`.        |
| 11       | RBAC Viewer Restriction              | PASS   | Viewer mendapat `403 Forbidden` saat membuka `/kepegawaian/pegawai`, sehingga daftar semua pegawai tidak terekspos.                                                                        |

## VERDICT: REJECT

Reasons:

- 10 dari 11 skenario lolos, tetapi Scenario 8 gagal karena halaman create pegawai me-return 500, jadi final QA tidak bisa di-approve.
- Kegagalan Scenario 8 bersumber dari query backend yang mengurutkan kolom `ref_jabatan.urutan` yang tidak ada di database aktif.

## Additional Findings

- Verifikasi read-only via `php artisan tinker` menunjukkan akun QA aktif adalah `admin@example.com` (admin) dan `viewer@example.com` (viewer), bukan domain `.test` seperti yang tertulis di brief.
- Viewer seed aktif ter-link ke pegawai `Ade Ramadan`, bukan `Fattahurridlo Al Ghany`, sehingga detail persona pada brief juga tidak sinkron dengan database saat ini.
