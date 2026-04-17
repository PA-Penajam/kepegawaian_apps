# Self-Service Pengajuan Perubahan Data — Design

**Tanggal:** 2026-04-17  
**Status:** Draft for Review  
**Scope:** Fase 5.2 workflow pengajuan perubahan biodata pribadi dan keluarga dengan approval validator  
**Pendekatan:** Satu tabel approval generik dengan snapshot JSON `before/after`, approval global validator, dan penerapan perubahan langsung saat approve  
**Konteks:** Melanjutkan roadmap fase 5, tetapi item 5.2 dipisah dari implementasi 5.1 karena membutuhkan desain domain, workflow approval, aturan dokumen, dan boundary role yang baru

---

## Tujuan

Membuka kemampuan pengajuan perubahan data untuk domain self-service tanpa memberi akses tulis langsung ke data efektif. Baik `pegawai` maupun `operator` dapat membuat usulan perubahan, tetapi hanya `validator` yang boleh memutuskan dan menerapkan perubahan ke tabel operasional asli.

Target fase pertama:

- mendukung biodata pribadi
- mendukung seluruh data keluarga
- menyimpan audit trail lengkap
- menampilkan riwayat beserta diff `before/after`
- memberi notifikasi aplikasi ke validator untuk antrean `pending`

Di luar scope fase ini:

- riwayat kepegawaian seperti pangkat, jabatan, pendidikan, dan data operasional lain
- notifikasi email/WA
- edit usulan oleh validator
- multi-level approval
- validator per unit kerja

---

## Keputusan Desain

### Opsi yang dipertimbangkan

1. **Satu tabel approval generik + snapshot JSON**
   - Paling fleksibel untuk banyak bentuk biodata.
   - Audit diff `before/after` mudah ditampilkan.
   - Mesin approval bisa dipakai bersama oleh `pegawai` dan `operator`.

2. **Tabel approval per domain**
   - Skema lebih ketat, tetapi jumlah tabel, model, dan controller bertambah besar.
   - Kurang cocok untuk fase pertama yang masih perlu bergerak cepat.

3. **Event sourcing penuh**
   - Audit sangat kuat, tetapi terlalu berat untuk kebutuhan fase ini.

### Pendekatan terpilih

Gunakan **satu tabel `pengajuan_perubahan_data`** sebagai ruang tunggu keputusan dan audit trail. Data operasional asli tetap berada di tabel yang sudah ada. Saat `approve`, service aplikasi membaca `after_payload` lalu menulis ke tabel asli dalam transaksi database yang sama.

---

## Boundary Domain

### Domain yang masuk fase pertama

- `profil_pribadi`
- `pasangan`
- `anak`
- `keluarga_lain`

### Sumber usulan

- `pegawai`
- `operator`

### Role keputusan

- `validator` global

### Aturan inti

- `pegawai` dan `operator` tidak boleh menulis langsung ke data efektif untuk domain yang masuk approval
- semua perubahan domain tersebut selalu menghasilkan record `pending`
- `validator` hanya boleh `approve` atau `reject`
- `reject` wajib menyertakan alasan
- `approve` langsung menulis ke data asli
- satu aksi hanya boleh punya satu pengajuan `pending`

---

## Model Data

### Tabel utama: `pengajuan_perubahan_data`

Kolom logis yang dibutuhkan:

- `id`
- `nomor_pengajuan`
- `jenis_pengaju`
  Nilai: `pegawai`, `operator`
- `pengaju_id`
  FK ke `pegawai`
- `validator_id`
  FK ke `pegawai`, nullable sampai diputus
- `domain`
  Nilai awal: `profil_pribadi`, `pasangan`, `anak`, `keluarga_lain`
- `aksi`
  Nilai: `create`, `update`, `delete`
- `target_type`
  Menyimpan model target, misalnya `pegawai` atau `keluarga`
- `target_id`
  Nullable untuk aksi `create`
- `status`
  Nilai: `pending`, `approved`, `rejected`
- `before_payload`
  Snapshot utuh data sebelum perubahan
- `after_payload`
  Snapshot utuh data usulan
- `changed_fields`
  Daftar field yang berubah agar diff lebih murah diolah
- `lampiran_paths`
  Daftar path dokumen pendukung
- `alasan_penolakan`
- `submitted_at`
- `approved_at`
- `rejected_at`
- `timestamps`

### Prinsip penyimpanan snapshot

- `before_payload` dan `after_payload` disimpan utuh per pengajuan
- frontend tidak menghitung diff langsung dari model mentah
- backend menurunkan `diff_items` yang siap tampil dari dua snapshot tersebut

### Relasi

- `pengaju` → `Pegawai`
- `validator` → `Pegawai`
- `target` → polymorphic ringan, atau kombinasi `target_type + target_id` tanpa morph relation penuh jika ingin implementasi lebih sederhana

---

## State Machine

Hanya ada tiga state pada fase pertama:

- `pending`
- `approved`
- `rejected`

Transisi yang diizinkan:

- `pending -> approved`
- `pending -> rejected`

Transisi yang tidak ada pada fase pertama:

- `draft`
- `cancelled`
- `revised`
- `reopened`

Konsekuensi:

- jika usulan ditolak, pengaju harus membuat usulan baru
- validator tidak dapat mengubah isi usulan sebelum approve

---

## Aturan Role

### Pegawai

- membuat pengajuan untuk data miliknya sendiri
- melihat daftar pengajuan
- melihat detail pengajuan, status, diff, dokumen, dan alasan penolakan

### Operator

- mengisi atau memperbarui data domain self-service melalui form kerja
- aksi simpan tidak langsung mengubah data efektif
- aksi simpan menghasilkan `pending request`
- melihat daftar usulan yang ia buat

### Validator

- melihat inbox global semua pengajuan `pending`
- melihat detail diff dan lampiran
- menolak dengan alasan wajib
- menyetujui dan memicu penerapan perubahan ke data asli

### Sistem

- menghasilkan nomor pengajuan
- membentuk snapshot `before/after`
- menurunkan `diff_items`
- menegakkan larangan duplicate `pending`
- mengirim notifikasi aplikasi ke validator

---

## Aturan Konflik dan Idempotensi

### Duplicate pending

Selama masih ada `pending`, sistem harus menolak usulan baru untuk aksi/target yang sama.

Aturan praktis:

- `profil_pribadi`
  Hanya satu `pending` untuk pegawai yang sama
- `pasangan`
  Satu `pending` per aksi pasangan untuk pegawai yang sama
- `anak`
  Satu `pending` per target anak untuk `update/delete`, dan satu `pending create` per draft anak yang sedang diproses
- `keluarga_lain`
  Aturan serupa dengan `anak`

Implementasi sebaiknya ditaruh di service domain, bukan hanya validasi UI.

### Atomic approve

Saat `approve`, dua hal wajib terjadi dalam satu transaksi:

1. tulis perubahan ke data asli
2. ubah status pengajuan menjadi `approved`

Jika penulisan data asli gagal, status pengajuan tetap `pending`.

---

## Aturan Dokumen Pendukung

### Wajib dokumen

- semua perubahan `pasangan`
- semua perubahan `anak`
- perubahan identitas utama pada `profil_pribadi`
  Contoh awal: `nama`, `nik`, `tempat_lahir`, `tanggal_lahir`, `status_perkawinan`

### Tidak wajib dokumen pada fase pertama

- perubahan profil ringan seperti kontak atau alamat, kecuali nanti diputuskan lain di level plan

### Implikasi desain

- validasi dokumen harus berbasis `domain + aksi + field yang berubah`
- `changed_fields` diperlukan bukan hanya untuk diff, tetapi juga untuk memutuskan kewajiban lampiran

---

## Aturan Bentuk Pengajuan

Satu pengajuan harus merepresentasikan **satu aksi**.

Contoh yang valid:

- ubah profil pribadi
- tambah anak
- ubah pasangan
- hapus anggota keluarga

Contoh yang tidak valid:

- ubah profil pribadi + tambah anak dalam satu submit
- ubah pasangan + hapus anak dalam satu submit

Ini penting agar audit trail dan approval tetap jelas.

---

## Penerapan ke Data Asli

### Saat approve

- `profil_pribadi`
  Update langsung record `pegawai`
- `pasangan`, `anak`, `keluarga_lain`
  Terapkan ke tabel keluarga/relasi yang sudah ada sesuai `aksi`
  - `create` membuat record baru
  - `update` mengubah record target
  - `delete` melakukan penghapusan sesuai pola yang sudah dipakai aplikasi

### Service aplikasi

Gunakan service khusus approval, misalnya:

- `SubmitPengajuanPerubahanDataService`
- `ApprovePengajuanPerubahanDataService`
- `RejectPengajuanPerubahanDataService`
- `PengajuanPerubahanDataDiffService`

Tujuannya agar controller tetap tipis dan logika conflict/approval tidak tercecer.

---

## UI dan Alur Inertia

### Halaman pegawai/operator

#### `self-service/pengajuan/index`

Menampilkan:

- daftar pengajuan milik pengaju
- status
- domain
- aksi
- waktu submit
- waktu keputusan
- validator

#### `self-service/pengajuan/create`

Menampilkan form usulan berdasarkan domain/aksi.

Pola Inertia:

- gunakan `useForm`
- andalkan redirect + validation errors standar Laravel
- state form tetap terjaga ketika validasi gagal

### Halaman validator

#### `kepegawaian/pengajuan/index`

Menampilkan:

- inbox global `pending`
- filter sederhana per domain/status/pengaju bila diperlukan
- badge jumlah pending sebagai notifikasi aplikasi

#### `kepegawaian/pengajuan/show`

Menampilkan:

- metadata pengajuan
- dokumen pendukung
- `before/after diff`
- tombol `approve`
- form `reject` dengan alasan wajib

---

## Bentuk Diff

Backend menyiapkan `diff_items` terstruktur, bukan frontend menghitung diff mentah dari JSON.

Struktur logis item diff:

- `field`
- `label`
- `before`
- `after`
- `change_type`
  Nilai: `added`, `updated`, `removed`, `unchanged` bila perlu

Untuk domain keluarga, diff perlu juga menyertakan konteks entitas, misalnya nama anak atau jenis relasi, agar validator tidak membaca JSON mentah.

---

## Otorisasi

### Policy

Buat `PengajuanPerubahanDataPolicy` untuk:

- melihat daftar/detail pengajuan sendiri
- melihat inbox validator
- approve
- reject

### FormRequest

Gunakan `FormRequest` untuk semua aksi mutasi:

- `StorePengajuanPerubahanDataRequest`
- `ApprovePengajuanPerubahanDataRequest`
- `RejectPengajuanPerubahanDataRequest`

`authorize()` harus memanfaatkan policy atau `user()->can(...)` agar ada defense-in-depth selain middleware route.

---

## Notifikasi Aplikasi

Fase pertama hanya membutuhkan notifikasi dalam aplikasi untuk validator.

Bentuk minimum:

- badge/count pending di navigasi validator
- indikator item baru di inbox

Email, WA, atau push notification belum masuk fase ini.

---

## Strategi Pengujian

Minimum test yang perlu ada di plan implementasi:

- pegawai bisa membuat pengajuan domain yang diizinkan
- operator membuat usulan yang tersimpan sebagai `pending`, bukan update langsung
- validator bisa melihat inbox global
- validator bisa approve, dan approve menulis ke data asli
- validator bisa reject, dan alasan wajib
- duplicate pending ditolak
- dokumen wajib untuk domain/field tertentu
- riwayat dan diff tampil di response Inertia
- pengaju hanya bisa melihat pengajuan miliknya sendiri

---

## Risiko dan Mitigasi

### Risiko 1: JSON snapshot terlalu longgar

Mitigasi:

- batasi domain fase pertama
- validasi bentuk `after_payload` per domain melalui DTO/normalizer atau service validator domain

### Risiko 2: aturan duplicate pending ambigu

Mitigasi:

- definisikan kunci konflik eksplisit per domain saat plan implementasi

### Risiko 3: approval menulis data keluarga salah target

Mitigasi:

- gunakan service domain khusus per aksi
- tambahkan test approve per domain dan per aksi

### Risiko 4: UI validator terlalu sulit membaca diff

Mitigasi:

- hitung diff di backend
- tampilkan label field manusiawi, bukan nama kolom mentah

---

## Hasil yang Diharapkan

Setelah fase ini selesai:

- pegawai dan operator tidak lagi mengubah data biodata/keluarga secara efektif tanpa approval
- validator menjadi satu-satunya titik keputusan
- semua keputusan punya jejak lengkap
- riwayat pengajuan bisa diaudit dengan diff yang jelas
- fondasi approval generik siap dipakai ulang untuk domain lain di fase berikutnya

---

## Catatan Lanjutan untuk Plan

Plan implementasi berikutnya harus memecah pekerjaan minimal ke area berikut:

- migration + model + enum status
- service submit/approve/reject/diff
- policy + FormRequest
- route + controller self-service
- route + controller validator
- halaman Inertia pegawai/operator
- halaman Inertia validator
- notifikasi aplikasi validator
- feature tests end-to-end
