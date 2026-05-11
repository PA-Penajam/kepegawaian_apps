# Decisions — sikep-p1-administrasi

## [2026-05-11] Session ses_1e7c87b4fffeYQ7x92284dD4WI

### Sequence Reset Policy (NomorSuratService)
- Reset per TAHUN per klasifikasi (bukan per bulan)
- Bulan romawi adalah bagian format, bukan reset trigger
- Hole policy: nomor yang di-release tidak di-backfill

### State Machine UsulanKenaikanPangkat
- 11 state: Draft → Diajukan → DiverifikasiKasubbag → DiverifikasiSekretaris → DitandatanganiKetua → DikirimBiro → MenungguSK → SelesaiSKTerbit (terminal happy)
- Terminal fail: Ditolak, Dibatalkan
- PerluPerbaikan → kembali ke Draft

### Approval Berjenjang
- 3 level: Kasubbag Kepegawaian → Sekretaris → Ketua Pengadilan
- Penandatangan surat KP: Ketua Pengadilan (dari config)

### Checklist
- Generic polimorfik via morphTo subject
- Template default: `checklist-kp-reguler` (dari config `sikep.kp.checklist_template_kode`)
- Auto-attach saat createDraft

### Riwayat Pangkat Invariant
- Hanya 1 row `is_aktif=true` per pegawai_id
- Unique partial index (sqlite: WHERE clause, mysql: generated column)
- Deactivate old SEBELUM insert new

### Scope P1
- Hanya KP reguler (bukan pilihan/prestasi)
- Hanya PNS (bukan PPPK)
- Hanya KP struktural (bukan JF/DUPAK)
- Cuti: audit saja, tidak modifikasi
