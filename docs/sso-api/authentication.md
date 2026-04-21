# Autentikasi — HMAC Signature

Setiap request ke API IAM kepegawaian-apps harus disertai tiga header berikut:

| Header | Tipe | Keterangan |
|--------|------|------------|
| `X-App-Key` | string | API Key aplikasi Anda (dari admin panel) |
| `X-Signature` | string | HMAC-SHA256 signature (lihat cara hitung di bawah) |
| `X-Timestamp` | string | Unix timestamp saat ini (detik, bukan milidetik) |

> Request yang timestamp-nya berbeda lebih dari **5 menit** dari waktu server akan ditolak secara otomatis. Pastikan jam server Anda sinkron (gunakan NTP).

---

## Formula Signature

```
payload  = METHOD + ":" + PATH + ":" + SORTED_QUERY + ":" + BODY_SHA256 + ":" + TIMESTAMP
signature = HMAC-SHA256(payload, API_SECRET)
```

**Penjelasan setiap komponen:**

| Komponen | Format | Contoh |
|----------|--------|--------|
| `METHOD` | HTTP method, huruf besar | `GET`, `POST` |
| `PATH` | Path URL tanpa query string | `/api/v1/iam/validate` |
| `SORTED_QUERY` | Query string, key di-sort A-Z | `permission=absensi%3Acreate` |
| `BODY_SHA256` | SHA-256 dari raw request body | `e3b0c44298...` (string kosong jika tidak ada body) |
| `TIMESTAMP` | Nilai `X-Timestamp` yang sama | `1745123456` |

> Untuk request tanpa query string, komponen `SORTED_QUERY` adalah **string kosong** (bukan dihilangkan).
> Untuk request tanpa body (GET), `BODY_SHA256` adalah SHA-256 dari string kosong: `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`

---

## Contoh Perhitungan Step-by-Step

**Skenario:** `GET /api/v1/iam/validate` tanpa query string

```
API_KEY    = "iam_abc123"
API_SECRET = "mysecret64charstring..."
TIMESTAMP  = "1745123456"

METHOD        = "GET"
PATH          = "/api/v1/iam/validate"
SORTED_QUERY  = ""
BODY_SHA256   = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"

payload = "GET:/api/v1/iam/validate::e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855:1745123456"

signature = HMAC-SHA256(payload, "mysecret64charstring...")
          = "a1b2c3d4e5f6..."  ← hasil hex lowercase
```

**Skenario:** `GET /api/v1/iam/check?permission=absensi:create`

```
SORTED_QUERY = "permission=absensi%3Acreate"   ← URL-encoded, key sorted

payload = "GET:/api/v1/iam/check:permission=absensi%3Acreate:e3b0c44...:1745123456"
```

**Skenario:** `POST /api/v1/iam/exchange-code` dengan body JSON

```
BODY        = '{"code":"abc123...64chars"}'
BODY_SHA256 = sha256('{"code":"abc123...64chars"}')

payload = "POST:/api/v1/iam/exchange-code::sha256_dari_body:1745123456"
```

---

## Contoh Header Lengkap

```http
GET /api/v1/iam/validate HTTP/1.1
Host: kepegawaian.pa-penajam.go.id
Authorization: Bearer 1|abc123sanctumtoken...
X-App-Key: iam_abc123
X-Timestamp: 1745123456
X-Signature: a1b2c3d4e5f6...
Content-Type: application/json
```

---

## Error Codes

| HTTP | Pesan | Penyebab |
|------|-------|----------|
| `401` | `Invalid credentials` | API Key tidak ditemukan / tidak aktif |
| `401` | `Invalid credentials` | Signature tidak cocok |
| `401` | `Invalid credentials` | Timestamp lebih dari 5 menit dari waktu server |

> Semua kegagalan autentikasi mengembalikan pesan generik `"Invalid credentials"` untuk menghindari information disclosure.

---

## Tips Implementasi

- **Jangan cache** timestamp — gunakan `time()` / `Date.now() / 1000` baru setiap request
- **Sort query parameter** sebelum di-encode: `ksort()` (PHP), `sorted()` (Python), `Object.keys().sort()` (JS)
- **Gunakan hex lowercase** untuk output HMAC (bukan base64)
- **Body hash** dihitung dari raw bytes sebelum serialisasi ulang — pastikan tidak ada whitespace tambahan

Lihat implementasi lengkap di masing-masing panduan framework:
→ [Laravel](./integration/laravel.md) · [CI4](./integration/ci4.md) · [FastAPI](./integration/fastapi.md) · [Express.js](./integration/express.md)
