# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

1. **Pegawai / ASN (Aparatur Sipil Negara)**: Mengakses portal self-service untuk melihat biodata, memantau saldo cuti tahunan/besar/lainnya, mengajukan permohonan cuti, mengusulkan kenaikan pangkat mandiri, memperbarui berkas/data pribadi, serta menerima notifikasi approval.
2. **Atasan Langsung & Pejabat Berwenang**: Melakukan telaah, verifikasi berkas, dan persetujuan (approval) bertingkat atas permohonan cuti dan usulan kenaikan pangkat bawahan.
3. **Pengelola Kepegawaian / Admin SDM**: Mengelola master data kepegawaian (biodata, riwayat jabatan, kepangkatan, pendidikan, diklat, penghargaan, hukuman disiplin), memonitor jadwal Kenaikan Gaji Berkala (KGB) & Kenaikan Pangkat (KP), mengonfigurasi saldo cuti awal tahun, serta mencetak form/SK resmi.
4. **Superadmin / IAM Administrator**: Mengelola identitas pengguna, integrasi SSO aplikasi eksternal, penetapan role & granular permissions, serta audit trail (activity log).

## Product Purpose

Menyediakan sistem informasi manajemen kepegawaian (SIMPEG) terpadu, formal, dan akuntabel bagi Pengadilan Agama Penajam / Lembaga Peradilan. Sistem ini mengotomatiskan siklus hidup ASN: mulai dari pengelolaan master data kepegawaian, layanan administrasi mandiri (cuti & kenaikan pangkat), pemantauan berkala (KGB & KP), hingga penyediaan single sign-on (SSO Identity Provider) yang aman dan berstandar regulasi peradilan.

## Positioning

Sistem kepegawaian instansi peradilan yang menggabungkan kepatuhan birokrasi peradilan (validasi berkas checklist ketat, persetujuan bertingkat formal, pembagian kuota cuti n, n-1, n-2 berlandaskan regulasi BKN/MA RI) dengan antarmuka web modern berkinerja tinggi, responsif, dan terintegrasi IAM/SSO.

## Operating Context

- Digunakan harian di lingkungan kantor pengadilan dan perangkat kerja dinas oleh aparatur peradilan.
- Beroperasi dalam alur kerja administrasi resmi: pengunggahan dokumen legal (SK Pangkat, SK Jabatan, Ijazah, Sertifikat), audit trail tindakan pegawai/admin, dan penerbitan dokumen cetak resmi (PDF Form Permohonan Cuti sesuai format BKN/MARI).
- Mengintegrasikan data autentikasi dan otorisasi ke subsistem lain (misal: sistem absensi presensi) melalui SSO / IAM API.

## Capabilities and Constraints

- **Master Data Kepegawaian**: Biodata, riwayat kepangkatan, jabatan, pendidikan, diklat fungsional/struktural, keluarga, penghargaan, hukuman disiplin, dan pengarsipan dokumen digital pegawai.
- **Modul Cuti**: Pengajuan cuti (Tahunan, Besar, Sakit, Melahirkan, Karena Alasan Penting, Diluar Tanggungan Negara), kalkulasi sisa saldo otomatis (N, N-1, N-2), approval bertingkat (Atasan Langsung & Pejabat Yang Berwenang Memberikan Cuti), dan ekspor formulir permohonan cuti PDF resmi.
- **Modul Kenaikan Pangkat & KGB**: Usulan kenaikan pangkat reguler/pilihan, monitoring eligibility KP/KGB periodik, verifikasi berkas berdasar Checklist Template dinamis, dan penetapan SK Admin.
- **Self-Service & Approval Inbox**: Dashboard mandiri pegawai, pengajuan perubahan data profil, dan kotak masuk persetujuan terpusat.
- **IAM & Akses Keamanan**: SSO OAuth2/Token Provider, role-based access control (RBAC) dengan granular permissions, rotasi secret/key, dan pencatatan riwayat aktivitas (`spatie/laravel-activitylog`).
- **Autentikasi & Keamanan**: Laravel Fortify dengan Two-Factor Authentication (2FA TOTP & Recovery Codes), verifikasi email, proteksi password, dan session guard.

## Brand Commitments

- **Identitas**: Sistem Informasi Kepegawaian Pengadilan Agama Penajam (PA Penajam).
- **Nuansa & Nada**: Formal, kredibel, tertib hukum, andal, dan menjunjung tinggi integritas aparatur peradilan.
- **Palet Visual Dasar**: Hijau yudisial/institusi peradilan (Forest/Deep Green) dengan aksen emas/warm amber yang elegan dan latar belakang bersih bernuansa institusional.

## Evidence on Hand

- Database schema dan relasi kepegawaian aktif di model Laravel (`Pegawai`, `CutiPengajuan`, `CutiSaldo`, `UsulanKenaikanPangkat`, `ChecklistTemplate`, `IamAplikasi`, `Role`, `Permission`, `Activity`).
- Template dokumen cuti resmi (`docs/form_cuti.docx`), spesifikasi dokumen (`docs/spesifikasi-dokumen.md`), aturan bisnis cuti & KGB (`docs/rules.md`).
- Komponen antarmuka React 19 + Inertia v2 + Tailwind CSS v4 + Radix UI / shadcn di `resources/js/`.

## Product Principles

1. **Akurasi & Integritas Dokumen**: Setiap data kepegawaian dan berkas lampiran harus memiliki riwayat yang jelas, status validasi eksplisit, dan jejak audit yang tidak dapat dimanipulasi.
2. **Kerapatan Informasi & Efisiensi Operasional (High-Density Utility)**: Tata letak data tabel dan formulir dirancang padat namun lapang (high information density), memudahkan staf SDM dan pimpinan menelaah informasi tanpa scrolling berlebih.
3. **Kejelasan Status & Tindakan Selanjutnya**: Indikator status (Draft, Diajukan, Disetujui Atasan, Ditolak, Selesai) harus sangat kontras, tegas, dan disertai aksi cepat yang kontekstual.
4. **Resilience & Konsistensi State**: Penanganan kondisi loading, empty state, error validasi, dan feedback aksi (toast/flash message) harus konsisten di seluruh modul.

## Accessibility & Inclusion

- Mendukung kontras tinggi yang jelas pada tema terang dan gelap.
- Navigasi keyboard yang mulus pada modal, dropdown, dan formulir data.
- Label formulir eksplisit dan teks bantuan kontekstual untuk meminimalkan kesalahan input data administrasi negara.
