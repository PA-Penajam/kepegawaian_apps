# SSO API — kepegawaian-apps

kepegawaian-apps berfungsi sebagai **IAM Hub / SSO Gateway** untuk seluruh ekosistem aplikasi PA Penajam. Aplikasi lain tidak mengelola autentikasi sendiri — mereka mendelegasikan login dan otorisasi ke kepegawaian-apps.

---

## Prasyarat

Sebelum integrasi, lakukan langkah berikut:

1. Login ke kepegawaian-apps sebagai **Super Admin**
2. Buka menu **IAM → Aplikasi → Daftarkan Aplikasi**
3. Isi nama, slug unik, dan URL base aplikasi Anda
4. Salin **API Key** dan **API Secret** yang ditampilkan — secret hanya muncul sekali
5. Simpan ke environment variables aplikasi Anda (lihat panduan framework di bawah)

---

## Alur SSO End-to-End

```
Aplikasi Klien                    kepegawaian-apps
     │                                   │
     │  1. User belum login              │
     │  → redirect ke:                   │
     │  GET /sso/login                   │
     │     ?app={slug}                   │
     │     &redirect={callback_url}      │
     │ ────────────────────────────────► │
     │                                   │  2. Jika user belum login di kepegawaian:
     │                                   │     tampilkan halaman login Fortify
     │                                   │
     │                                   │  3. Setelah login berhasil:
     │                                   │     generate one-time code (64 char, 60 detik)
     │                                   │     simpan ke iam_sso_codes
     │                                   │
     │  4. Redirect ke callback_url      │
     │     ?code={one_time_code}         │
     │ ◄──────────────────────────────── │
     │                                   │
     │  5. Server aplikasi klien         │
     │     POST /api/v1/iam/exchange-code│
     │     { code: "..." }               │
     │     + HMAC headers                │
     │ ────────────────────────────────► │
     │                                   │  6. Validasi code: single-use, belum expired,
     │                                   │     milik app yang benar
     │                                   │     Mark used_at = now()
     │                                   │
     │  7. Response: Bearer token        │
     │     { token, expires_at }         │
     │ ◄──────────────────────────────── │
     │                                   │
     │  8. Simpan token di session       │
     │     server-side (bukan cookie)    │
     │                                   │
     │  9. Setiap request protected:     │
     │     GET /api/v1/iam/validate      │
     │     Authorization: Bearer {token} │
     │     + HMAC headers                │
     │ ────────────────────────────────► │
     │                                   │
     │  10. Response: user + permissions │
     │ ◄──────────────────────────────── │
```

> **Mengapa one-time code?** Token di URL masuk ke browser history, server log, dan Referer header. Code pendek (60 detik, single-use) yang diexchange server-to-server tidak pernah terekspos ke browser.

---

## Keamanan — 4 Layer

| Layer | Mekanisme | Keterangan |
|-------|-----------|------------|
| 1 | **Sanctum Token** | Identifikasi user, TTL 8 jam |
| 2 | **X-App-Key** | Identifikasi aplikasi pemanggil |
| 3 | **X-Signature (HMAC-SHA256)** | Anti-tampering + anti-replay (window 5 menit) |
| 4 | **One-time SSO Code** | Code expire 60 detik, single-use |

---

## Environment Variables

Tambahkan ke `.env` aplikasi klien:

```env
IAM_URL=https://kepegawaian.pa-penajam.go.id
IAM_API_KEY=iam_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## Dokumentasi Lanjutan

| File | Isi |
|------|-----|
| [authentication.md](./authentication.md) | Cara menghitung HMAC signature |
| [endpoints.md](./endpoints.md) | Referensi lengkap semua endpoint |
| [integration/laravel.md](./integration/laravel.md) | Panduan integrasi Laravel |
| [integration/ci4.md](./integration/ci4.md) | Panduan integrasi CodeIgniter 4 |
| [integration/fastapi.md](./integration/fastapi.md) | Panduan integrasi FastAPI (Python) |
| [integration/express.md](./integration/express.md) | Panduan integrasi Express.js |
| [openapi.yaml](./openapi.yaml) | OpenAPI 3.0 spec (semua endpoint) |

---

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| `POST /api/v1/iam/exchange-code` | 10 req/menit per IP |
| `GET /api/v1/iam/validate` | 120 req/menit per IP |
| `GET /api/v1/iam/check` | 120 req/menit per IP |
| `POST /api/v1/iam/logout` | 120 req/menit per IP |

> **Tips:** Cache hasil `/api/v1/iam/validate` di sisi aplikasi klien selama 60 detik untuk menghindari round-trip berulang ke kepegawaian-apps.
