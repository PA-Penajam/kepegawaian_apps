# Runbook Operasional — Modul Cuti & Izin

> Panduan operasional untuk administrator sistem dalam mengelola modul Cuti & Izin.

## Daftar Isi

- [Inisialisasi Saldo Awal Tahun](#inisialisasi-saldo-awal-tahun)
- [Manual Carry-Over Replay](#manual-carry-over-replay)
- [Webhook Dead Letter Handling](#webhook-dead-letter-handling)
- [Reassign Approver](#reassign-approver)
- [Disaster Recovery: Rebuild Saldo](#disaster-recovery-rebuild-saldo)
- [Expire Stale Drafts](#expire-stale-drafts)
- [Troubleshooting](#troubleshooting)

---

## Inisialisasi Saldo Awal Tahun

### Via Admin UI

1. Login sebagai admin dengan permission `cuti.saldo.view-all`
2. Navigasi ke `/admin/cuti/saldo`
3. Klik **Inisialisasi Saldo** yang mengarah ke `/admin/cuti/saldo/init`
4. Pilih tahun dan pegawai yang akan diinisialisasi
5. Sistem akan memanggil `SaldoLedgerService::kreditAlokasi()` secara idempotent — jika kredit sudah ada untuk kombinasi `(nip, jenis_kode, tahun)`, operasi akan di-skip

### Via Artisan (Carry-Over Otomatis)

```bash
php artisan cuti:carry-over
```

Command ini dijalankan otomatis oleh scheduler setiap **1 Januari pukul 00:05**. Proses:
1. Ambil semua pegawai aktif
2. Untuk setiap pegawai dengan jenis cuti `saldo_driven` (CT):
   - Hitung sisa saldo tahun lalu dari ledger
   - Buat alokasi baru di tahun berjalan (`hak_default_per_tahun` = 12 hari)
   - Buat bucket carry-over untuk sisa saldo tahun lalu (jika ada)
3. Semua operasi idempotent — aman dijalankan ulang

### Via Code (Manual Bulk Init)

Untuk inisialisasi manual melalui tinker atau seeder:

```php
$saldoService = app(\App\Services\Cuti\SaldoLedgerService::class);

// Inisialisasi saldo CT untuk satu pegawai
$saldoService->kreditAlokasi(
    nip: '199001012015031001',
    jenisKode: 'CT',
    tahun: 2026,
    hari: 12,
    keterangan: 'Inisialisasi saldo awal tahun 2026'
);
```

---

## Manual Carry-Over Replay

### Skenario: Scheduler Gagal Jalan pada 1 Januari

Jika scheduler tidak berjalan pada tanggal 1 Januari (server down, cron tidak aktif, dll.), carry-over dapat dijalankan manual:

```bash
# Carry-over untuk semua pegawai
php artisan cuti:carry-over
```

### Carry-Over untuk Satu Pegawai

```bash
# Target NIP tertentu
php artisan cuti:carry-over --nip=199001012015031001
```

### Verifikasi Hasil

Setelah menjalankan carry-over, verifikasi hasilnya:

```sql
-- Cek alokasi tahun baru sudah terbuat
SELECT * FROM cuti_alokasi_tahunan
WHERE tahun_hak = 2026
ORDER BY pegawai_nip;

-- Cek ledger kredit carry-over
SELECT * FROM cuti_saldo_ledger
WHERE tahun_hak = 2026
  AND jenis_transaksi = 'kredit'
  AND keterangan LIKE '%carry%'
ORDER BY created_at DESC;
```

---

## Webhook Dead Letter Handling

Modul cuti menggunakan transactional outbox pattern untuk mengirim event ke sistem eksternal (misalnya `attendance-qr-system`). Event yang gagal dikirim setelah beberapa percobaan akan masuk status `dead_letter`.

### Cek Dead Letter

```sql
SELECT ed.id, e.event_type, e.payload, ed.status, ed.attempts, ed.last_error, ed.updated_at
FROM cuti_event_deliveries ed
JOIN cuti_events e ON e.id = ed.event_id
WHERE ed.status = 'dead_letter'
ORDER BY ed.updated_at DESC;
```

### Manual Retry

1. Identifikasi delivery yang perlu di-retry dari query di atas
2. Reset status dan attempts:

```sql
UPDATE cuti_event_deliveries
SET status = 'pending', attempts = 0, last_error = NULL
WHERE id = '<delivery_id>';
```

3. Jalankan dispatcher untuk memproses ulang:

```bash
php artisan cuti:dispatch-events
```

### Investigasi Penyebab

Jika dead letter terus berulang, periksa:

1. **URL consumer tidak bisa diakses** — cek `config/cuti.php` → `consumers.attendance-qr-system.webhook_url`
2. **Secret key tidak valid** — cek `CUTI_ATTENDANCE_SHARED_SECRET_ENCRYPTED` di `.env`
3. **Consumer mengembalikan error** — lihat `last_error` pada tabel `cuti_event_deliveries`
4. **Timeout** — consumer terlalu lama merespons, pertimbangkan naikkan timeout

---

## Reassign Approver

### Skenario: Atasan Langsung Mutasi/Rotasi

Ketika atasan langsung atau pejabat berwenang dimutasi, pengajuan yang sedang aktif perlu di-reassign ke pejabat pengganti.

### Via UI (Admin)

1. Login sebagai admin dengan permission `cuti.pengajuan.reassign`
2. Buka detail pengajuan di `/cuti/pengajuan/{id}`
3. Klik tombol **Reassign Approver**
4. Pilih role yang di-reassign (`atasan_langsung` atau `pejabat_berwenang`)
5. Pilih pegawai pengganti dan isi alasan
6. Submit

### Via API

```
POST /cuti/pengajuan/{id}/reassign-approver
```

Body:
```json
{
    "role": "atasan_langsung",
    "new_nip": "198501012010011001",
    "alasan": "Mutasi ke unit kerja lain per SK No. 123/2026"
}
```

### Audit Trail

Setiap reassignment dicatat di tabel `cuti_pengajuan_approver_history`:

```sql
SELECT * FROM cuti_pengajuan_approver_history
WHERE pengajuan_id = '<pengajuan_id>'
ORDER BY created_at DESC;
```

Kolom yang tercatat:
- `role` — role yang di-reassign
- `from_pegawai_nip` — NIP lama
- `to_pegawai_nip` — NIP baru
- `alasan` — alasan reassignment
- `aktor_pegawai_nip` — admin yang melakukan reassignment

---

## Disaster Recovery: Rebuild Saldo

### Prinsip: Saldo Selalu Derivable dari Ledger

Saldo CT tidak pernah disimpan sebagai kolom counter. Nilai saldo selalu dihitung dari `SUM(jumlah_hari)` pada tabel `cuti_saldo_ledger`. Ini berarti:

- **Tidak ada data saldo yang bisa "rusak"** selama ledger utuh
- **Saldo bisa diverifikasi kapan saja** tanpa proses rebuild

### Verifikasi Saldo

```sql
-- Hitung saldo aktual dari ledger
SELECT
    pegawai_nip,
    jenis_cuti_kode,
    tahun_hak,
    SUM(jumlah_hari) AS saldo_aktual
FROM cuti_saldo_ledger
WHERE pegawai_nip = '199001012015031001'
  AND jenis_cuti_kode = 'CT'
GROUP BY pegawai_nip, jenis_cuti_kode, tahun_hak
ORDER BY tahun_hak;
```

### Validasi Silang dengan Alokasi

```sql
-- Bandingkan saldo ledger dengan hak_awal dari alokasi
SELECT
    a.pegawai_nip,
    a.tahun_hak,
    a.hak_awal,
    COALESCE(SUM(l.jumlah_hari), 0) AS saldo_dari_ledger,
    a.hak_awal + COALESCE(SUM(l.jumlah_hari), 0) AS selisih_check
FROM cuti_alokasi_tahunan a
LEFT JOIN cuti_saldo_ledger l
    ON l.pegawai_nip = a.pegawai_nip
    AND l.jenis_cuti_kode = a.jenis_cuti_kode
    AND l.tahun_hak = a.tahun_hak
WHERE a.jenis_cuti_kode = 'CT'
GROUP BY a.pegawai_nip, a.tahun_hak, a.hak_awal
HAVING saldo_dari_ledger < 0 OR saldo_dari_ledger > a.hak_awal;
```

Jika ada baris yang muncul dari query di atas, investigasi transaksi ledger untuk pegawai tersebut.

### Mendetail Transaksi Ledger

```sql
-- Lihat semua transaksi ledger untuk pegawai tertentu
SELECT
    jenis_transaksi,
    tahun_hak,
    jumlah_hari,
    pengajuan_id,
    keterangan,
    created_at
FROM cuti_saldo_ledger
WHERE pegawai_nip = '199001012015031001'
  AND jenis_cuti_kode = 'CT'
ORDER BY created_at;
```

---

## Expire Stale Drafts

### Otomatis (Scheduled)

Command `cuti:expire-drafts` dijalankan scheduler setiap hari pukul **00:30**. Pengajuan yang berstatus `DRAFT` lebih dari 7 hari akan otomatis diubah ke `DIBATALKAN`.

### Manual

```bash
# Expire draft yang sudah lebih dari 7 hari (default)
php artisan cuti:expire-drafts

# Expire draft yang sudah lebih dari N hari
php artisan cuti:expire-drafts --days=3
```

### Verifikasi

```sql
-- Cek draft yang sudah expired
SELECT id, pegawai_nip, jenis_cuti_kode, state, created_at
FROM cuti_pengajuan
WHERE state = 'DIBATALKAN'
  AND cancelled_at IS NOT NULL
ORDER BY cancelled_at DESC
LIMIT 20;
```

---

## Troubleshooting

### "Saldo tidak cukup" (`SaldoTidakCukupException`)

**Penyebab umum:**
1. Carry-over belum dijalankan untuk tahun berjalan
2. Ada pengajuan lain yang masih berstatus `DIAJUKAN`/`DIVERIFIKASI`/`DISETUJUI_ATASAN` (saldo sudah di-debit pending)
3. Admin belum melakukan inisialisasi saldo

**Langkah investigasi:**

```sql
-- Cek saldo aktual dari ledger
SELECT tahun_hak, SUM(jumlah_hari) AS saldo
FROM cuti_saldo_ledger
WHERE pegawai_nip = '<nip>'
  AND jenis_cuti_kode = 'CT'
GROUP BY tahun_hak;

-- Cek apakah ada debit_pending yang belum selesai
SELECT id, state, jumlah_hari_kerja
FROM cuti_pengajuan
WHERE pegawai_nip = '<nip>'
  AND jenis_cuti_kode = 'CT'
  AND state IN ('DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN');
```

**Solusi:**
- Jika carry-over belum jalan: `php artisan cuti:carry-over --nip=<nip>`
- Jika saldo memang habis: admin bisa melakukan penyesuaian via `/admin/cuti/saldo` → Adjust

---

### "Transisi tidak valid" (`TransitionTidakValidException`)

**Penyebab umum:**
1. Pengajuan sudah tidak berada di state yang diharapkan (misal sudah di-approve oleh user lain)
2. Approver NIP tidak cocok dengan yang ditunjuk di pengajuan

**Langkah investigasi:**

```sql
-- Cek state saat ini
SELECT id, state, atasan_langsung_current_nip, pejabat_berwenang_current_nip
FROM cuti_pengajuan
WHERE id = '<pengajuan_id>';

-- Cek riwayat state
SELECT state_from, state_to, aktor_pegawai_nip, created_at
FROM cuti_pengajuan_state_history
WHERE pengajuan_id = '<pengajuan_id>'
ORDER BY created_at;
```

**Solusi:**
- Pastikan aktor memiliki permission yang sesuai untuk state saat ini
- Jika approver sudah mutasi, gunakan fitur [Reassign Approver](#reassign-approver)

---

### "Overlap pengajuan" (`OverlapPengajuanException`)

**Penyebab umum:**
Pegawai mengajukan cuti untuk periode yang tumpang tindih dengan pengajuan aktif lainnya.

**Langkah investigasi:**

```sql
-- Cek pengajuan aktif untuk pegawai
SELECT p.id, p.state, p.jenis_cuti_kode, pp.tanggal_mulai, pp.tanggal_selesai
FROM cuti_pengajuan p
JOIN cuti_pengajuan_periode pp ON pp.pengajuan_id = p.id
WHERE p.pegawai_nip = '<nip>'
  AND p.state NOT IN ('DIBATALKAN', 'DITOLAK_KEPEGAWAIAN', 'DITOLAK_ATASAN', 'DITOLAK_PEJABAT', 'DICABUT_SETELAH_DISETUJUI')
ORDER BY pp.tanggal_mulai;
```

**Solusi:**
- Pegawai harus membatalkan pengajuan yang overlap terlebih dahulu
- Atau admin membatalkan pengajuan lama jika sudah tidak relevan

---

### Event Webhook Tidak Terkirim

**Langkah investigasi:**

```sql
-- Cek event yang belum di-dispatch
SELECT id, event_type, dispatched_at, created_at
FROM cuti_events
WHERE dispatched_at IS NULL
ORDER BY created_at;

-- Cek delivery attempts
SELECT ed.*, e.event_type
FROM cuti_event_deliveries ed
JOIN cuti_events e ON e.id = ed.event_id
WHERE ed.status IN ('pending', 'failed', 'dead_letter')
ORDER BY ed.updated_at DESC;
```

**Solusi:**
- Pastikan scheduler berjalan: `php artisan schedule:list`
- Manual dispatch: `php artisan cuti:dispatch-events`
- Untuk dead letter, lihat bagian [Webhook Dead Letter Handling](#webhook-dead-letter-handling)

---

### PDF Gagal Generate

**Penyebab umum:**
1. Puppeteer/Browsershot tidak terinstall di server
2. Node.js tidak tersedia

**Langkah investigasi:**
```bash
# Cek apakah Node tersedia
node --version

# Cek apakah puppeteer terinstall
ls node_modules/puppeteer
```

**Solusi:**
- Install puppeteer: `npm install puppeteer`
- Pastikan `PUPPETEER_EXECUTABLE_PATH` di `.env` mengarah ke binary Chrome/Chromium yang benar (jika menggunakan custom path)
