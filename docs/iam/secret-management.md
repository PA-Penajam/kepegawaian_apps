# IAM Secret Management

Dokumen ini menjelaskan lifecycle plaintext API secret di IAM Hub: kapan di-generate, di-cache, dan diaudit.

## Lifecycle Plaintext Secret

```
NEVER_GENERATED → RECOVERABLE (15m TTL) → COMMITTED (steady state)
                       ↓ (regenerate)
                  RECOVERABLE (new TTL)
```

### State Definisi

- **NEVER_GENERATED**: Aplikasi baru di-create, sebelum service.generateAndStore() selesai.
- **RECOVERABLE**: Plaintext ada di cache `iam:secret:recovery:{app_id}`, modal bisa di-recover.
- **COMMITTED**: Cache miss (TTL habis atau di-acknowledge). HMAC verification tetap jalan dari DB.

## Cache Convention

| Key | Value | TTL | Store |
|---|---|---|---|
| `iam:secret:recovery:{app_id}` | Plaintext secret 64 char | 15 menit | `CACHE_STORE=database` |

App ID pakai ULID (dari `HasUlids` trait), tidak bisa collision walaupun slug di-rename.

## Audit Events

Semua event ditulis ke table `activity_log` dengan `log_name='iam_audit'`.

### Query Examples

**Lihat semua event regenerate dalam 7 hari terakhir:**

```sql
SELECT created_at, causer_id, subject_id, properties
FROM activity_log
WHERE log_name = 'iam_audit'
  AND event = 'secret.regenerated'
  AND created_at >= NOW() - INTERVAL '7 days'
ORDER BY created_at DESC;
```

**Hitung HMAC failure per aplikasi (untuk threat detection):**

```sql
SELECT
    subject_id AS app_id,
    properties->>'reason' AS reason,
    COUNT(*) AS failure_count
FROM activity_log
WHERE log_name = 'iam_audit'
  AND event = 'hmac.verification_failed'
  AND created_at >= NOW() - INTERVAL '1 hour'
GROUP BY subject_id, properties->>'reason'
ORDER BY failure_count DESC;
```

**Trace siapa yang regenerate aplikasi tertentu:**

```sql
SELECT
    al.created_at,
    p.nama_lengkap AS causer_name,
    al.properties->>'previous_key_prefix' AS old_key,
    al.properties->>'ip' AS ip
FROM activity_log al
LEFT JOIN pegawai p ON p.id = al.causer_id
WHERE al.log_name = 'iam_audit'
  AND al.event = 'secret.regenerated'
  AND al.subject_id = '01HQX...your_app_ulid';
```

## Rate Limit

Endpoint `POST /iam/aplikasi/{id}/regenerate-key` di-protect named limiter `iam-regenerate`:
- 5 request per jam per user_id (fallback: per IP kalau guest).
- Request ke-6 dapat flash error "Anda telah melampaui batas regenerasi kunci (5 per jam)".

Reset limiter manual via Tinker:

```bash
php artisan tinker
>>> RateLimiter::clear('iam-regenerate:'.$user_id);
```

## Backward Compatibility

HMAC verification dari aplikasi client **tidak berubah**. Server tetap pakai `Crypt::decryptString($app->api_secret_hash)` (sumber DB). Cache hanya layer recovery, tidak operational.

## Manual Smoke Test Checklist

- [ ] Create aplikasi baru → modal tampil dengan secret.
- [ ] Tutup modal → reload show page → block kuning visible → klik "Tampilkan Ulang" → modal tampil lagi dengan secret yang sama.
- [ ] Klik "Saya sudah simpan" → reload → block kuning hilang.
- [ ] Tunggu >15 menit → block kuning hilang otomatis.
- [ ] Regenerate 6 kali → request ke-6 dapat flash error.
- [ ] Query `activity_log` → ada 4 jenis event sesuai aksi.
- [ ] Send signature salah ke endpoint protected → ada entry `hmac.verification_failed`.
