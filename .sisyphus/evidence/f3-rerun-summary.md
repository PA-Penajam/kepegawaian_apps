# F3 QA Re-run Summary

Date: 2026-03-15
Fix Applied: RefJabatan::orderBy('urutan') -> orderBy('nama') di PegawaiController.php

## Results

| Scenario | Description                 | Status | Notes                                                                                                                                                        |
| -------- | --------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1        | Login Admin                 | PASS   | Login `admin@example.com` berhasil dan redirect ke `/dashboard`.                                                                                             |
| 2        | Dashboard Statistik         | PASS   | Halaman dashboard (`/dashboard`) menampilkan card Total Pegawai, Golongan, Unit Kerja, serta section KGB dan Kenaikan Pangkat.                               |
| 3        | Pegawai Index               | PASS   | Halaman index menampilkan tabel pegawai (15 baris), search input, dan kontrol filter.                                                                        |
| 4        | Search & Filter             | PASS   | Search + filter query (`search`, `golongan`, `unit_kerja`) memfilter hasil (15 -> 1) dan state filter aktif terlihat.                                        |
| 5        | Pegawai Show/Detail         | PASS   | Halaman detail pegawai terbuka normal dan menampilkan 9 tab.                                                                                                 |
| 6        | Tab Navigation              | PASS   | Tab `Riwayat Pangkat` bisa dibuka dan kontennya tampil.                                                                                                      |
| 7        | Monitoring KGB              | PASS   | Halaman monitoring KGB menampilkan tabel dengan kolom status.                                                                                                |
| 8        | Create Pegawai Form         | PASS   | Route `/kepegawaian/pegawai/create` tidak lagi 500; field form tampil; data dropdown referensi termuat (`refJabatan=15`, `refPangkat=17`, `refUnitKerja=8`). |
| 9        | Monitoring Kenaikan Pangkat | PASS   | Halaman monitoring kenaikan pangkat menampilkan tabel normal.                                                                                                |
| 10       | Self-Service (Viewer)       | PASS   | Login `viewer@example.com` berhasil; halaman `/self-service` menampilkan data diri.                                                                          |
| 11       | Access Control              | PASS   | Viewer mendapatkan `403 Forbidden` saat akses `/kepegawaian/pegawai/create`.                                                                                 |

## Verdict: APPROVE

Semua 11 scenario QA PASS pada rerun ini. Blocker Scenario 8 (error 500 pada form create pegawai) terverifikasi sudah resolved.
