# Refactor UI/UX Detail Pegawai: Neo-Brutalist App Launcher

Berdasarkan kesepakatan dari *mockup* HTML `mockup_retro_launcher.html`, kita akan menghapus pendekatan **"Satu Halaman Memuat 9 Tabel Data"** (*Tabs*) dan menggantinya menjadi pendekatan **"App Launcher (Dashboard Menu)"**.

## 🛠 Perubahan Arsitektur Tampilan
Halaman `/self-service/detail` tidak akan lagi merender semua data tabel di belakang layar. Halaman ini murni akan bertugas sebagai layar menu interaktif:
1. **Header Profil**: Menampilkan data singkat (Foto, Nama, NIP).
2. **Grid Launcher Kartu (Neo-Brutalism)**: Menampilkan menu-menu kategori data:
   - Biodata
   - Keluarga
   - Riwayat Jabatan
   - Riwayat Pangkat
   - Pendidikan
   - Diklat
   - Penghargaan
   - Hukuman Disiplin
   - Dokumen
   
Setiap kartu akan dirender sebagai *Card* dengan efek bayangan retro (`drop-shadow-[4px_4px_0_rgba(0,0,0,1)]`) tebal dan animasi tekanan saat dikenai kursor.

## 🔗 Mekanisme Routing (Navigasi)
Saat pengguna mengeklik sebuah Kartu, mereka akan langsung dipindahkan (*redirect/navigate via Inertia Link*) ke URL halaman pengelolaannya:
- 📝 **Biodata:** Mengarah ke `PegawaiController.edit(pegawai.id)`
- 👨‍👩‍👧 **Keluarga:** Mengarah ke `KeluargaController.index(pegawai.id)`
- 💼 **Riwayat Jabatan:** Mengarah ke `RiwayatJabatanController.index(pegawai.id)`
- *(dan seterusnya untuk kategori lain...)*

## 📂 File yang Akan Dieksekusi

1. **`resources/js/pages/self-service/detail.tsx`**
   - Akan didekonstruksi secara total. 
   - Membuang pemanggilan `<PegawaiDetailTabs />`.
   - Menggantinya dengan komponen internal Grid Link bergaya *Retro Launcher*.
   
2. **`resources/js/components/pegawai-detail-tabs.tsx`** & Foldernya (`pegawai-tabs/`)
   - File konfigurasi Tabs akan menjadi usang (*obsolete*) dan dihapus secara bersih beserta seluruh anak tab-nya (`biodata-tab.tsx`, `keluarga-tab.tsx`, `detail-tab-card.tsx`), karena Detail Pegawai tidak lagi me-render tabel *inline*. Ini secara radikal **memotong ratusan baris kode** dan performa React akan sangat ringan.

> [!CAUTION]  
> Mengingat penghapusan skala besar ini (karena mengubah UI tab jadi menu navigasi eksternal), pastikan akses URL `Route::get` ke tiap control (*KeluargaController, dsb*) mendukung pengunjungan murni (tanpa modal) dan berfungsi dengan aman sebagaimana aslinya sebelum kita singkirkan *Tabs Component*-nya.

Persetujuan implementasi siap dijalankan!
