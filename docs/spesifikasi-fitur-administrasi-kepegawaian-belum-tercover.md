# Spesifikasi Fitur Tambahan Modul Administrasi Kepegawaian

## 1. Pendahuluan

### 1.1 Latar Belakang

Aplikasi `kepegawaian-apps` telah memiliki fondasi utama administrasi kepegawaian, meliputi pengelolaan data pegawai, riwayat pangkat, riwayat jabatan, riwayat pendidikan, riwayat diklat, dokumen pegawai, pengajuan perubahan data, monitoring kenaikan pangkat, monitoring KGB, IAM/SSO, dan audit trail.

Fitur Manajemen Absensi dipisahkan ke aplikasi tersendiri, yaitu `attendance-qr-system`, dan dalam dokumen ini dianggap telah menjadi bagian dari ekosistem administrasi kepegawaian. Oleh karena itu, fitur absensi tidak dimasukkan sebagai gap pengembangan `kepegawaian-apps`.

Berdasarkan spesifikasi Modul Administrasi Kepegawaian, masih terdapat beberapa fitur yang belum tersedia atau belum lengkap pada `kepegawaian-apps`, terutama pada proses administrasi end-to-end seperti cuti, izin, mutasi, pensiun, workflow kenaikan pangkat, checklist berkas, dan penerbitan dokumen administrasi.

### 1.2 Tujuan Dokumen

Dokumen ini disusun sebagai acuan pengembangan fitur tambahan pada `kepegawaian-apps` agar lebih sesuai dengan kebutuhan Modul Administrasi Kepegawaian.

Tujuan dokumen ini adalah:

1. Mengidentifikasi fitur administrasi kepegawaian yang belum tercover.
2. Menentukan kebutuhan fungsional per fitur.
3. Menyediakan alur proses awal untuk pengembangan sistem.
4. Menetapkan prioritas implementasi.
5. Menjadi dasar penyusunan backlog, desain database, UI, dan pengujian.

### 1.3 Dasar Hukum

Pengembangan fitur tambahan pada `kepegawaian-apps` didasarkan pada regulasi dan ketentuan berikut:

1. **Peraturan Mahkamah Agung (PERMA) Nomor 7 Tahun 2015** tentang Organisasi dan Tata Kerja Kepaniteraan dan Kesekretariatan Peradilan — menjadi dasar tupoksi Kepala Sub Bagian Kepegawaian, Organisasi, dan Tata Laksana yang mencakup pengelolaan mutasi, kenaikan pangkat, cuti, pensiun, dan penerbitan surat keputusan.
2. **Peraturan Badan Kepegawaian Negara (BKN) Nomor 8 Tahun 2019** tentang Indeks Profesionalitas ASN — menjadi dasar pengukuran kualitas pegawai berdasarkan dimensi kualifikasi, kompetensi, kinerja, dan kedisiplinan.
3. **SK Sekretaris Mahkamah Agung No. 27101/SEK/SK.RA1.3/X/2025** tentang Penetapan IKU 2025–2029 — menjadi dasar penyelarasan target kinerja pegawai dengan IKU organisasi.
4. **Peraturan BKN Nomor 4 Tahun 2025** tentang periode kenaikan pangkat PNS — menjadi dasar penyesuaian sistem kenaikan pangkat menjadi 12 periode per tahun.
5. **PERMA Nomor 8 Tahun 2016** tentang Pengawasan Melekat (Wasmev) — menjadi dasar dokumentasi pembinaan preventif dan korektif serta laporan pengawasan melekat.
6. **Peraturan perundang-undangan lainnya terkait Manajemen ASN** — termasuk kebijakan terbaru manajemen cuti PPPK dan PNS, ketentuan BUP, serta peraturan mutasi dan pensiun yang berlaku.

## 2. Ruang Lingkup

### 2.1 Termasuk dalam Ruang Lingkup

Fitur yang termasuk dalam dokumen ini adalah:

1. Manajemen Cuti & Izin.
2. Workflow Mutasi Pegawai.
3. Workflow Pensiun Pegawai.
4. Workflow Kenaikan Pangkat.
5. Penyesuaian periode kenaikan pangkat 12 kali per tahun.
6. Checklist berkas administrasi.
7. Generate dokumen, SK, dan surat administrasi.
8. Monitoring proses administrasi kepegawaian.
9. Pelaporan administrasi kepegawaian.

### 2.2 Tidak Termasuk dalam Ruang Lingkup

Fitur berikut tidak termasuk dalam ruang lingkup pengembangan dokumen ini:

1. Manajemen absensi harian.
2. Rekap kehadiran otomatis.
3. QR attendance.
4. Integrasi mesin absensi.
5. Laporan absensi detail.

Fitur absensi dianggap telah ditangani oleh `attendance-qr-system`.

## 3. Kondisi Sistem Saat Ini

### 3.1 Fitur yang Sudah Ada di `kepegawaian-apps`

| Fitur | Status |
|---|---|
| Manajemen data pegawai | Sudah ada |
| Riwayat pangkat | Sudah ada |
| Riwayat jabatan | Sudah ada |
| Riwayat pendidikan | Sudah ada |
| Riwayat diklat | Sudah ada |
| Dokumen pegawai | Sudah ada |
| Pengajuan perubahan data | Sudah ada |
| Monitoring kenaikan pangkat | Sebagian |
| Monitoring KGB | Sudah ada |
| IAM/SSO/RBAC | Sudah ada |
| Activity log/audit trail | Sudah ada |

### 3.2 Fitur yang Belum Lengkap

| Fitur | Status |
|---|---|
| Manajemen cuti & izin | Belum ada |
| Workflow mutasi | Belum lengkap |
| Workflow pensiun | Belum lengkap |
| Workflow kenaikan pangkat | Sebagian |
| Periode kenaikan pangkat 12 kali/tahun | Belum sesuai |
| Checklist berkas administrasi | Belum ada |
| Generate dokumen/SK administrasi | Belum ada |

## 4. Kebutuhan Fungsional

## 4.1 Modul Manajemen Cuti & Izin

### 4.1.1 Tujuan

Menyediakan sistem digital untuk pengajuan, verifikasi, persetujuan, pencatatan, dan penerbitan dokumen cuti/izin pegawai.

### 4.1.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Kepala Sub Bagian Kepegawaian & Ortala bertugas menyiapkan bahan pelaksanaan urusan perizinan dan penerbitan surat perintah cuti.
2. **Kebijakan terbaru manajemen cuti PPPK dan PNS** — Sistem harus mendukung penyesuaian dengan kebijakan terbaru terkait manajemen cuti PPPK dan PNS sebagaimana disebutkan dalam spesifikasi dokumen utama.
3. **Peraturan perundang-undangan terkait cuti ASN** — Termasuk Peraturan Pemerintah Nomor 11 Tahun 2017 tentang Manajemen Pegawai Negeri Sipil (pengaturan cuti), serta peraturan pelaksanaannya.

### 4.1.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| CUTI-01 | Pegawai dapat mengajukan cuti atau izin melalui sistem. |
| CUTI-02 | Sistem menyediakan jenis cuti: tahunan, sakit, melahirkan, alasan penting, besar, dan lainnya. |
| CUTI-03 | Sistem mendukung pengajuan izin tidak masuk, izin keluar kantor, dan izin terlambat. |
| CUTI-04 | Sistem mencatat tanggal mulai, tanggal selesai, jumlah hari, alasan, dan lampiran. |
| CUTI-05 | Sistem melakukan validasi sisa cuti. |
| CUTI-06 | Atasan atau pejabat berwenang dapat menyetujui atau menolak pengajuan. |
| CUTI-07 | Sistem menyimpan riwayat persetujuan. |
| CUTI-08 | Sistem dapat menerbitkan surat cuti/izin. |
| CUTI-09 | Sistem menyediakan rekap cuti per pegawai dan per periode. |
| CUTI-10 | Sistem mendukung perbedaan aturan PNS dan PPPK bila diperlukan. |

### 4.1.4 Alur Proses

1. Pegawai mengisi form pengajuan cuti/izin.
2. Sistem memvalidasi data dan sisa cuti.
3. Pengajuan masuk ke inbox atasan atau validator.
4. Atasan/validator menyetujui atau menolak.
5. Jika disetujui, sistem menghasilkan dokumen surat cuti/izin.
6. Riwayat pengajuan tersimpan dalam profil pegawai.

### 4.1.5 Data Minimal

| Data | Keterangan |
|---|---|
| Pegawai | Pegawai pemohon cuti/izin. |
| Jenis cuti/izin | Kategori pengajuan. |
| Tanggal mulai | Tanggal awal cuti/izin. |
| Tanggal selesai | Tanggal akhir cuti/izin. |
| Jumlah hari | Jumlah hari yang diajukan. |
| Alasan | Alasan pengajuan. |
| Lampiran | Dokumen pendukung. |
| Status pengajuan | Draft, diajukan, disetujui, ditolak, dan lainnya. |
| Pejabat penyetuju | Atasan/pejabat yang memutuskan. |
| Tanggal persetujuan | Waktu pengambilan keputusan. |
| Nomor surat | Nomor dokumen cuti/izin. |
| File surat | Dokumen hasil generate/upload. |

## 4.2 Modul Workflow Mutasi Pegawai

### 4.2.1 Tujuan

Menyediakan alur digital untuk pencatatan dan pengelolaan mutasi pegawai, baik mutasi jabatan, unit kerja, maupun perpindahan antarinstansi/satuan kerja.

### 4.2.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Kepala Sub Bagian Kepegawaian & Ortala bertugas menyiapkan bahan pelaksanaan urusan mutasi pegawai dan penataan organisasi.
2. **Peraturan Pemerintah Nomor 11 Tahun 2017** tentang Manajemen Pegawai Negeri Sipil — mengatur ketentuan mutasi, perpindahan, dan penempatan ASN.
3. **Peraturan BKN terkait mutasi** — ketentuan teknis pelaksanaan mutasi yang dikeluarkan oleh BKN.

### 4.2.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| MUT-01 | Admin/staf kepegawaian dapat membuat usulan mutasi pegawai. |
| MUT-02 | Sistem mencatat jenis mutasi: jabatan, unit kerja, antar satuan kerja, masuk, keluar. |
| MUT-03 | Sistem mencatat jabatan/unit asal dan jabatan/unit tujuan. |
| MUT-04 | Sistem menyediakan checklist berkas mutasi. |
| MUT-05 | Sistem mendukung upload dokumen pendukung. |
| MUT-06 | Sistem menyediakan workflow verifikasi dan approval. |
| MUT-07 | Sistem menerbitkan atau menyimpan SK mutasi. |
| MUT-08 | Jika mutasi disetujui, riwayat jabatan/unit kerja pegawai otomatis diperbarui. |
| MUT-09 | Sistem menyediakan monitoring status mutasi. |
| MUT-10 | Sistem menyediakan laporan mutasi periodik. |

### 4.2.4 Status Proses

Status proses mutasi minimal terdiri dari:

1. Draft.
2. Diajukan.
3. Dalam verifikasi.
4. Perlu perbaikan.
5. Disetujui.
6. Ditolak.
7. Selesai.
8. Dibatalkan.

### 4.2.5 Data Minimal

| Data | Keterangan |
|---|---|
| Pegawai | Pegawai yang dimutasi. |
| Jenis mutasi | Jabatan, unit kerja, masuk, keluar, atau lainnya. |
| Unit asal | Unit kerja sebelum mutasi. |
| Unit tujuan | Unit kerja setelah mutasi. |
| Jabatan asal | Jabatan sebelum mutasi. |
| Jabatan tujuan | Jabatan setelah mutasi. |
| TMT mutasi | Tanggal mulai tugas setelah mutasi. |
| Dasar hukum | Dasar keputusan mutasi. |
| Berkas pendukung | Dokumen mutasi. |
| Status proses | Status workflow. |
| SK mutasi | Dokumen akhir mutasi. |

## 4.3 Modul Workflow Pensiun Pegawai

### 4.3.1 Tujuan

Menyediakan sistem monitoring dan pengelolaan administrasi pensiun pegawai secara terstruktur, terdokumentasi, dan mudah dipantau.

### 4.3.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Kepala Sub Bagian Kepegawaian & Ortala bertugas menyiapkan bahan pelaksanaan urusan pensiun pegawai.
2. **Undang-Undang Nomor 5 Tahun 2014** tentang ASN — mengatur ketentuan pensiun ASN termasuk BUP, pensiun dini, dan pensiun lainnya.
3. **Peraturan Pemerintah Nomor 11 Tahun 2017** tentang Manajemen PNS — mengatur ketentuan teknis pengelolaan pensiun.
4. **Peraturan BKN terkait pensiun** — ketentuan pelaksanaan administrasi pensiun yang dikeluarkan oleh BKN.

### 4.3.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| PEN-01 | Sistem menampilkan daftar pegawai mendekati BUP. |
| PEN-02 | Sistem memberikan notifikasi pegawai yang akan pensiun. |
| PEN-03 | Admin/staf kepegawaian dapat membuat proses administrasi pensiun. |
| PEN-04 | Sistem menyediakan checklist berkas pensiun. |
| PEN-05 | Sistem mendukung upload dokumen pensiun. |
| PEN-06 | Sistem mencatat status proses pensiun. |
| PEN-07 | Sistem menyimpan SK pensiun. |
| PEN-08 | Sistem memperbarui status pegawai menjadi pensiun setelah proses selesai. |
| PEN-09 | Sistem menyediakan laporan pegawai akan pensiun. |
| PEN-10 | Sistem menyediakan riwayat pensiun dalam profil pegawai. |

### 4.3.4 Monitoring Pensiun

Sistem minimal menampilkan kategori monitoring berikut:

1. Pegawai pensiun dalam 24 bulan.
2. Pegawai pensiun dalam 12 bulan.
3. Pegawai pensiun dalam 6 bulan.
4. Pegawai pensiun dalam 3 bulan.
5. Pegawai dengan berkas belum lengkap.
6. Pegawai dengan SK pensiun sudah terbit.

### 4.3.5 Data Minimal

| Data | Keterangan |
|---|---|
| Pegawai | Pegawai yang akan pensiun. |
| Tanggal BUP | Tanggal batas usia pensiun. |
| Jenis pensiun | BUP, dini, meninggal, atau lainnya. |
| Tanggal usulan | Tanggal proses diajukan. |
| Checklist berkas | Kelengkapan dokumen pensiun. |
| Status proses | Status workflow pensiun. |
| Nomor SK pensiun | Nomor dokumen keputusan. |
| Tanggal SK pensiun | Tanggal dokumen keputusan. |
| File SK pensiun | Dokumen hasil upload/generate. |

## 4.4 Modul Workflow Kenaikan Pangkat

### 4.4.1 Tujuan

Melengkapi fitur monitoring kenaikan pangkat yang sudah ada agar menjadi workflow administrasi kenaikan pangkat yang utuh, mulai dari eligibility, usulan, verifikasi berkas, hingga pembaruan riwayat pangkat.

### 4.4.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Kepala Sub Bagian Kepegawaian & Ortala bertugas menyiapkan bahan pelaksanaan urusan kenaikan pangkat.
2. **Peraturan BKN Nomor 4 Tahun 2025** tentang periode kenaikan pangkat PNS — menjadi dasar utama penyesuaian sistem menjadi 12 periode per tahun, menggantikan sistem lama yang hanya mengacu pada periode April dan Oktober.
3. **Peraturan Pemerintah Nomor 11 Tahun 2017** tentang Manajemen PNS — mengatur ketentuan kenaikan pangkat secara umum.
4. **SK Sekretaris Mahkamah Agung No. 27101/SEK/SK.RA1.3/X/2025** tentang IKU 2025–2029 — menjadi dasar penyelarasan target kinerja dengan kenaikan pangkat.

### 4.4.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| KP-01 | Sistem mendukung 12 periode kenaikan pangkat dalam 1 tahun. |
| KP-02 | Sistem menampilkan pegawai yang memenuhi syarat kenaikan pangkat. |
| KP-03 | Sistem menyediakan workflow usulan kenaikan pangkat. |
| KP-04 | Sistem menyediakan checklist berkas kenaikan pangkat. |
| KP-05 | Sistem mendukung upload dokumen pendukung. |
| KP-06 | Sistem mencatat status proses usulan. |
| KP-07 | Sistem menyediakan approval/verifikasi berjenjang. |
| KP-08 | Sistem mencatat nomor dan tanggal usulan. |
| KP-09 | Sistem menyimpan dokumen/SK kenaikan pangkat. |
| KP-10 | Jika proses selesai, riwayat pangkat pegawai otomatis diperbarui. |
| KP-11 | Sistem menyediakan notifikasi deadline usulan. |
| KP-12 | Sistem menyediakan laporan kenaikan pangkat per periode. |

### 4.4.4 Periode Kenaikan Pangkat

Sesuai Peraturan BKN Nomor 4 Tahun 2025, sistem harus mendukung 12 periode kenaikan pangkat per tahun dengan periode bulanan sebagai berikut:

1. Januari.
2. Februari.
3. Maret.
4. April.
5. Mei.
6. Juni.
7. Juli.
8. Agustus.
9. September.
10. Oktober.
11. November.
12. Desember.

### 4.4.5 Status Proses Kenaikan Pangkat

Status proses kenaikan pangkat minimal terdiri dari:

1. Eligible.
2. Belum diajukan.
3. Draft usulan.
4. Diajukan.
5. Verifikasi berkas.
6. Perlu perbaikan.
7. Disetujui.
8. Ditolak.
9. SK terbit.
10. Selesai.

### 4.4.6 Data Minimal

| Data | Keterangan |
|---|---|
| Pegawai | Pegawai yang diusulkan naik pangkat. |
| Pangkat saat ini | Pangkat/golongan terakhir. |
| Pangkat tujuan | Pangkat/golongan yang diusulkan. |
| TMT pangkat terakhir | Dasar eligibility. |
| Periode usulan | Bulan/tahun periode kenaikan pangkat. |
| Nomor usulan | Nomor dokumen usulan. |
| Tanggal usulan | Tanggal pengajuan. |
| Checklist berkas | Kelengkapan dokumen. |
| Status proses | Status workflow. |
| SK kenaikan pangkat | Dokumen akhir. |

## 4.5 Modul Checklist Berkas Administrasi

### 4.5.1 Tujuan

Menyediakan daftar kelengkapan dokumen untuk setiap proses administrasi kepegawaian agar proses lebih tertib, terukur, dan mudah diaudit.

### 4.5.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Menyimpan dan memelihara data kepegawaian (SK, berkas pribadi, riwayat jabatan) secara digital agar mudah diakses.
2. **Peraturan BKN terkait kelengkapan berkas** — Setiap proses administrasi kepegawaian (kenaikan pangkat, mutasi, pensiun, cuti) memiliki ketentuan kelengkapan berkas yang harus dipenuhi.
3. **Prinsip akuntabilitas dan audit trail** — Sejalan dengan kebutuhan untuk memastikan setiap proses kepegawaian terdokumentasi, transparan, dan dapat diaudit sebagaimana diamanatkan dalam spesifikasi dokumen utama.

### 4.5.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| BERKAS-01 | Admin dapat membuat template checklist berkas. |
| BERKAS-02 | Checklist dapat dikaitkan dengan jenis proses: cuti, izin, mutasi, pensiun, dan kenaikan pangkat. |
| BERKAS-03 | Sistem mencatat status setiap berkas: belum ada, ada, valid, perlu perbaikan. |
| BERKAS-04 | Sistem mendukung upload lampiran per item checklist. |
| BERKAS-05 | Validator dapat memberi catatan per berkas. |
| BERKAS-06 | Sistem menampilkan persentase kelengkapan berkas. |
| BERKAS-07 | Proses tidak dapat dilanjutkan jika berkas wajib belum lengkap. |
| BERKAS-08 | Checklist tersimpan sebagai bagian dari riwayat proses pegawai. |

### 4.5.4 Contoh Checklist Kenaikan Pangkat

1. SK CPNS.
2. SK PNS.
3. SK pangkat terakhir.
4. SK jabatan terakhir.
5. SKP 2 tahun terakhir.
6. Sertifikat diklat jika diperlukan.
7. Dokumen pendukung lainnya.

### 4.5.5 Contoh Checklist Pensiun

1. SK CPNS.
2. SK PNS.
3. SK pangkat terakhir.
4. Data keluarga.
5. Kartu pegawai.
6. Dokumen BUP.
7. Surat pengantar.
8. Dokumen pendukung lainnya.

## 4.6 Modul Generate Dokumen/SK Administrasi

### 4.6.1 Tujuan

Memudahkan pembuatan dokumen administrasi kepegawaian berdasarkan data yang sudah tersedia di sistem.

### 4.6.2 Dasar Pembuatan

1. **PERMA Nomor 7 Tahun 2015** — Kepala Sub Bagian Kepegawaian & Ortala bertugas menyiapkan konsep surat keputusan, surat perintah, dan dokumen tata laksana lainnya.
2. **Prinsip dokumentasi digital** — Sejalan dengan kebutuhan untuk menyimpan dan memelihara data kepegawaian (SK, berkas pribadi, riwayat jabatan) secara digital agar mudah diakses.
3. **Kebutuhan pelaporan periodik** — Laporan kepegawaian sesuai periode (bulanan, triwulanan, semesteran, tahunan) memerlukan dokumen/SK yang terstruktur dan terdokumentasi.

### 4.6.3 Fitur Utama

| Kode | Kebutuhan |
|---|---|
| DOK-01 | Sistem menyediakan template dokumen administrasi. |
| DOK-02 | Template dapat menggunakan variabel data pegawai. |
| DOK-03 | Sistem dapat menghasilkan dokumen PDF. |
| DOK-04 | Sistem mencatat nomor surat/dokumen. |
| DOK-05 | Sistem menyimpan dokumen ke arsip pegawai. |
| DOK-06 | Sistem mendukung preview dokumen sebelum final. |
| DOK-07 | Sistem mendukung revisi dokumen. |
| DOK-08 | Sistem mendukung status dokumen: draft, final, ditandatangani. |
| DOK-09 | Sistem dapat mengaitkan dokumen dengan proses cuti, izin, mutasi, pensiun, atau kenaikan pangkat. |

### 4.6.4 Jenis Dokumen Awal

1. Surat cuti.
2. Surat izin.
3. Surat pengantar mutasi.
4. SK mutasi.
5. Surat pengantar pensiun.
6. Checklist pensiun.
7. Surat usulan kenaikan pangkat.
8. Berita acara/verifikasi berkas.
9. Dokumen administrasi lainnya.

## 5. Kebutuhan Role dan Hak Akses

| Role | Hak Akses |
|---|---|
| Admin Kepegawaian | Mengelola seluruh proses administrasi. |
| Staf Kepegawaian | Input data, upload berkas, membuat usulan. |
| Validator/Verifikator | Memeriksa dan memvalidasi berkas. |
| Atasan Langsung | Menyetujui atau menolak pengajuan. |
| Kepala Sub Bagian Kepegawaian & Ortala | Monitoring, persetujuan, dan evaluasi. |
| Sekretaris/Pimpinan | Monitoring dan laporan. |
| Pegawai | Mengajukan cuti/izin dan melihat status proses pribadi. |

## 6. Kebutuhan Laporan

Sistem perlu menyediakan laporan berikut:

| Kode | Laporan |
|---|---|
| LAP-01 | Laporan cuti pegawai. |
| LAP-02 | Laporan izin pegawai. |
| LAP-03 | Laporan mutasi pegawai. |
| LAP-04 | Laporan pegawai akan pensiun. |
| LAP-05 | Laporan proses pensiun. |
| LAP-06 | Laporan kenaikan pangkat per periode. |
| LAP-07 | Laporan kelengkapan berkas administrasi. |
| LAP-08 | Laporan proses yang tertunda. |
| LAP-09 | Laporan dokumen/SK yang sudah diterbitkan. |

Format laporan minimal mendukung:

1. PDF.
2. Excel.
3. Filter periode.
4. Filter unit kerja.
5. Filter status proses.
6. Filter jenis administrasi.

## 7. Kebutuhan Non-Fungsional

### 7.1 Keamanan

1. Sistem menggunakan autentikasi yang sudah tersedia.
2. Hak akses mengikuti RBAC/IAM.
3. Setiap perubahan status harus tercatat dalam audit trail.
4. File dokumen harus disimpan secara aman.
5. Dokumen final tidak boleh diubah tanpa mekanisme revisi.

### 7.2 Audit Trail

Sistem wajib mencatat:

1. Pembuat pengajuan.
2. Perubahan status.
3. Validator.
4. Approver.
5. Waktu persetujuan/penolakan.
6. Catatan perbaikan.
7. Upload, update, dan penghapusan dokumen.

### 7.3 Usability

1. Setiap proses memiliki status yang jelas.
2. Pengguna dapat melihat progress proses.
3. Dashboard menampilkan proses yang perlu tindakan.
4. Form dibuat ringkas dan sesuai kebutuhan administrasi.

### 7.4 Integrasi

1. Terintegrasi dengan data pegawai yang sudah ada.
2. Terintegrasi dengan dokumen pegawai.
3. Terintegrasi dengan riwayat pangkat/jabatan bila proses selesai.
4. Absensi dianggap tersedia melalui `attendance-qr-system`.

## 8. Prioritas Implementasi

### 8.1 Prioritas 1

1. Manajemen Cuti & Izin.
2. Workflow Kenaikan Pangkat 12 periode.
3. Checklist Berkas Administrasi.

### 8.2 Prioritas 2

1. Workflow Pensiun.
2. Workflow Mutasi.
3. Generate dokumen/SK administrasi.

### 8.3 Prioritas 3

1. Dashboard monitoring proses administrasi.
2. Laporan periodik.
3. Notifikasi deadline.
4. Integrasi lanjutan dengan sistem absensi untuk ringkasan disiplin/IP ASN.

## 9. Ringkasan Fitur yang Perlu Ditambahkan

| No | Fitur | Status Saat Ini | Target |
|---|---|---|---|
| 1 | Manajemen Cuti & Izin | Belum ada | Modul penuh. |
| 2 | Workflow Mutasi | Data pendukung ada | Workflow lengkap. |
| 3 | Workflow Pensiun | Data BUP ada | Monitoring + proses pensiun. |
| 4 | Workflow Kenaikan Pangkat | Monitoring ada | Workflow usulan lengkap. |
| 5 | Kenaikan Pangkat 12 Periode | Belum sesuai | Sesuai BKN 4/2025. |
| 6 | Checklist Berkas | Belum ada | Per proses administrasi. |
| 7 | Generate Dokumen/SK | Belum ada | Template + PDF + arsip. |
| 8 | Laporan Administrasi | Sebagian | Laporan per proses. |

## 10. Penutup

Dengan asumsi Manajemen Absensi sudah ditangani oleh `attendance-qr-system`, pengembangan `kepegawaian-apps` perlu difokuskan pada penyempurnaan proses administrasi kepegawaian end-to-end.

Fitur yang paling mendesak adalah Manajemen Cuti & Izin, Workflow Kenaikan Pangkat 12 periode, dan Checklist Berkas Administrasi karena ketiganya menjadi fondasi utama dalam pelaksanaan tugas Sub Bagian Kepegawaian, Organisasi, dan Tata Laksana.

Jika fitur-fitur ini ditambahkan, `kepegawaian-apps` akan lebih sesuai dengan spesifikasi Modul Administrasi Kepegawaian dan dapat berfungsi bukan hanya sebagai database pegawai, tetapi sebagai sistem administrasi proses kepegawaian yang lengkap, terdokumentasi, dan dapat diaudit.
