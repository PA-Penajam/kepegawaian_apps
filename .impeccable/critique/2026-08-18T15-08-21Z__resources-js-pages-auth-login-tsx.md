---
target: the login page
total_score: 32
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
timestamp: 2026-08-18T15-08-21Z
slug: resources-js-pages-auth-login-tsx
---
Method: dual-agent (A: 0e73b9b6-6290-4d67-bc1e-aa3acd3df490 · B: 2a3b573b-6dc1-4505-91e4-0a9e614c4683)

### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3/4 | State processing & spinner jelas; belum ada indikator Caps Lock aktif. |
| 2 | Match System / Real World | 4/4 | Terminologi ASN formal (NIP, Password, PA Penajam, MA RI). |
| 3 | User Control and Freedom | 3/4 | Toggle intip kata sandi & link beranda ada; belum ada tombol clear input NIP. |
| 4 | Consistency and Standards | 4/4 | Token desain OKLCH konsisten, semantik Radix UI rapi, reduced-motion penuh. |
| 5 | Error Prevention | 3/4 | Autofocus & reset password saat gagal ada; NIP belum dibatasi numeric mode / 18 digit. |
| 6 | Recognition Rather Than Recall | 3/4 | Label & placeholder jelas; belum ada panduan format 18 digit NIP. |
| 7 | Flexibility and Efficiency | 3/4 | Alur keyboard tab efisien & autocomplete siap; bisa ditambah deteksi paste NIP berspasi. |
| 8 | Aesthetic and Minimalist Design | 4/4 | Tata letak bersih, rasio kontras tinggi, ambient glow halus, tanpa ornamen berlebih. |
| 9 | Error Recovery | 3/4 | Animasi shake error banner & aria-invalid jelas; info throttling bisa diperjelas. |
| 10 | Help and Documentation | 2/4 | Ada tautan Lupa password; belum ada kontak helpdesk kepegawaian jika terkunci. |
| **Total** | | **32/40** | **Baik (Solid)** |

---

### Design Specificity Verdict

**LLM Assessment**: Fondasi visual telah dibangun sangat solid dengan tema warna *Deep Forest Green* dan *Warm Gold* yang elegan. Namun, ikon logo masih generik (`AppLogoIcon`) dan judul masih bernuansa *starter kit* umum. Menggantinya dengan lambang resmi Mahkamah Agung / PA Penajam dan label formal SIMPEG akan mengangkat kewibawaan instansi peradilan secara paripurna.

**Deterministic Scan**: Detektor otomatis menghasilkan **0 temuan** (*Clean pass*). Seluruh token warna berbasis semantic OKLCH, motion menghormati `useReducedMotion`, dan atribut aksesibilitas ARIA terkonfigurasi lengkap.

---

### Overall Impression
Halaman Login telah bertransformasi dari neobrutalisme menjadi antarmuka korporat peradilan modern yang bersih, cepat, dan sangat aksesibel. Penyempurnaan berikutnya terletak pada penguatan identitas resmi peradilan, guardrail input NIP 18 digit, dan kenyamanan bantuan pengguna.

---

### What's Working
1. **Aksesibilitas & Disiplin Motion**: Implementasi `useReducedMotion()` yang konsisten, label ARIA dinamis, serta navigasi keyboard yang mulus.
2. **Kerapian Token & Sistem Desain**: Penggunaan token warna semantik OKLCH secara menyeluruh tanpa nilai hex hardcoded atau anti-pattern visual.
3. **Ergonomi Input Responsif**: Tombol sentuh 40px yang nyaman, toggle intip kata sandi beranimasi, dan adaptasi viewport yang presisi dari 320px hingga 4K.

---

### Priority Issues

- **[P1] Logo & Branding Yudisial Kurang Menonjol**: Logo masih menggunakan representasi geometri umum, belum menampilkan Lambang Resmi Mahkamah Agung RI / PA Penajam.
  - *Why it matters*: Menurunkan kesan keaslian portal resmi kedinasan bagi ASN dan pimpinan.
  - *Fix*: Integrasikan lambang resmi instansi peradilan dan pertegas badge aplikasi "SIMPEG PA Penajam".
  - *Suggested command*: `/impeccable bolder`

- **[P2] Input NIP Belum Memiliki Numeric Guardrail & Sanitasi Spasi**: Input NIP masih bertipe teks biasa tanpa `inputMode="numeric"` dan batas 18 karakter.
  - *Why it matters*: ASN yang menyalin NIP dengan spasi/strip atau mengetik di ponsel/tablet akan mengalami kesalahan format.
  - *Fix*: Tambahkan `inputMode="numeric"`, `maxLength={18}`, dan sanitasi otomatis penghapusan karakter non-digit saat paste.
  - *Suggested command*: `/impeccable harden`

- **[P2] Ketiadaan Saluran Bantuan / Kontak Sub Bagian Kepegawaian**: Tidak ada keterangan kontak bantuan jika akun terkunci atau NIP belum terdaftar.
  - *Why it matters*: ASN mengalami kebingungan saat terjadi kendala autentikasi di luar kendali mandiri.
  - *Fix*: Tambahkan teks bantuan kecil di bawah form yang mengarahkan ke Helpdesk Kepegawaian & Ortala PA Penajam.
  - *Suggested command*: `/impeccable clarify`

- **[P3] Indikator Tombol Caps Lock Belum Tersedia**: Belum ada pendeteksian tombol Caps Lock pada kolom password.
  - *Why it matters*: Caps Lock yang tidak sengaja aktif di PC kantor adalah penyebab #1 kesalahan input kata sandi.
  - *Fix*: Tambahkan listener `getModifierState('CapsLock')` pada `PasswordInput` untuk memunculkan badge peringatan.
  - *Suggested command*: `/impeccable delight`

---

### Persona Red Flags
- **Pegawai Baru / CPNS**: Menyalin NIP dari SK dengan format bertanda spasi (`19980101 202401 1 001`) yang dapat memicu error jika tidak disanitasi otomatis.
- **Pimpinan / Hakim**: Mengakses login lewat ponsel di sela persidangan mendapati keyboard alfabet alih-alih numeric keypad karena ketiadaan `inputMode="numeric"`.
- **Pengguna Terkunci (Rate Limited)**: Pegawai yang lupa password tidak mengetahui berapa lama harus menunggu saat terkena limitasi frekuensi login.

---

### Minor Observations & Questions to Consider
- Judul formulir dapat dipertegas langsung menjadi *"SIMPEG PA Penajam"* dengan subjudul *"Portal Kepegawaian Pengadilan Agama Penajam"*.
- Halaman alur autentikasi pendukung (seperti `two-factor-challenge.tsx`) perlu diselaraskan teksnya ke Bahasa Indonesia.
