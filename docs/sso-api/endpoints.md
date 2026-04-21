# Referensi Endpoint API

Base URL: `https://kepegawaian.pa-penajam.go.id`

Semua endpoint API memerlukan header HMAC. Lihat [authentication.md](./authentication.md) untuk cara menghitungnya.

---

## 1. SSO Login Entry Point

### `GET /sso/login`

Redirect browser user ke halaman login terpusat kepegawaian-apps. Ini adalah **web route** (bukan API) — dibuka dari browser, bukan dipanggil server-to-server.

**Query Parameters:**

| Parameter | Wajib | Keterangan |
|-----------|-------|------------|
| `app` | Ya | Slug aplikasi yang terdaftar di admin panel |
| `redirect` | Ya | URL callback di aplikasi Anda (harus domain yang sama dengan URL terdaftar) |

**Contoh:**
```
GET /sso/login?app=attendance&redirect=https://attendance.pa-penajam.go.id/sso/callback
```

**Alur:**
- Jika user **belum login** di kepegawaian-apps → tampilkan halaman login, lanjutkan SSO setelah berhasil
- Jika user **sudah login** → langsung generate code dan redirect ke `redirect` URL

**Response:** HTTP redirect ke `{redirect}?code={64_char_code}`

**Error:**
| Kondisi | Response |
|---------|----------|
| Slug `app` tidak ditemukan / tidak aktif | `404 Not Found` |
| `redirect` bukan URL valid | `422 Unprocessable Entity` |
| Host `redirect` berbeda dari URL aplikasi terdaftar | `422 Unprocessable Entity` |

---

## 2. Tukar Code → Token

### `POST /api/v1/iam/exchange-code`

Tukar one-time SSO code menjadi Sanctum Bearer token. Dipanggil **server-to-server** (bukan dari browser).

**Rate limit:** 10 request/menit per IP

**Headers:**

```http
X-App-Key: {api_key}
X-Timestamp: {unix_timestamp}
X-Signature: {hmac_sha256}
Content-Type: application/json
```

> Endpoint ini **tidak** memerlukan `Authorization: Bearer` karena user belum punya token.

**Request Body:**

```json
{
  "code": "abc123...64chars"
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `code` | string (64 char) | One-time code dari query parameter `?code=` |

**Response 200 — Berhasil:**

```json
{
  "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz...",
  "token_type": "Bearer",
  "expires_at": 1745152256
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `token` | string | Sanctum Bearer token — simpan di session server-side |
| `token_type` | string | Selalu `"Bearer"` |
| `expires_at` | integer | Unix timestamp kapan token expired (default: 8 jam dari sekarang) |

**Response 400 — Code tidak valid:**

```json
{
  "message": "Invalid or expired code"
}
```

| Kondisi | Kode |
|---------|------|
| Code sudah digunakan | `400` |
| Code expired (>60 detik) | `400` |
| Code milik aplikasi lain | `400` |
| Format code salah (bukan 64 char) | `422` |

---

## 3. Validasi Token

### `GET /api/v1/iam/validate`

Validasi Bearer token dan ambil informasi user beserta roles & permissions untuk aplikasi pemanggil.

**Rate limit:** 120 request/menit per IP

> **Rekomendasi:** Cache response endpoint ini di sisi aplikasi klien selama **60 detik** menggunakan key `iam_{sha256(token)}` untuk mengurangi round-trip ke kepegawaian-apps.

**Headers:**

```http
Authorization: Bearer {sanctum_token}
X-App-Key: {api_key}
X-Timestamp: {unix_timestamp}
X-Signature: {hmac_sha256}
```

**Response 200 — Token valid:**

```json
{
  "user": {
    "id": "01JRXXXXXXXXXXXXXXXXXXXXXXXX",
    "name": "Budi Santoso",
    "email": "budi@pa-penajam.go.id",
    "nip": "199107132020121003"
  },
  "roles": ["operator"],
  "permissions": ["absensi:create", "rekap:read"],
  "token_expires_at": 1745152256
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `user.id` | string (ULID) | ID unik pegawai di kepegawaian-apps |
| `user.name` | string | Nama lengkap |
| `user.email` | string | Email pegawai |
| `user.nip` | string | NIP 18 digit |
| `roles` | array of string | Slug role yang dimiliki user di aplikasi pemanggil |
| `permissions` | array of string | Slug permission turunan dari role (format: `resource:action`) |
| `token_expires_at` | integer\|null | Unix timestamp expired token; null jika token tanpa expiry |

> Jika user tidak memiliki role di aplikasi pemanggil, `roles` dan `permissions` akan berupa **array kosong** — bukan error 403. Keputusan akses ada di tangan aplikasi klien.

**Response 401 — Token tidak valid / expired:**

```json
{
  "message": "Unauthenticated."
}
```

---

## 4. Cek Satu Permission

### `GET /api/v1/iam/check`

Cek apakah user memiliki satu permission tertentu. Cocok untuk middleware sederhana yang tidak butuh semua data dari `/validate`.

**Rate limit:** 120 request/menit per IP

**Headers:** sama seperti `/validate`

**Query Parameters:**

| Parameter | Wajib | Keterangan |
|-----------|-------|------------|
| `permission` | Ya | Slug permission (format: `resource:action`) |

**Contoh:**
```
GET /api/v1/iam/check?permission=absensi:create
```

**Response 200:**

```json
{
  "allowed": true,
  "permission": "absensi:create"
}
```

| Field | Tipe | Keterangan |
|-------|------|------------|
| `allowed` | boolean | `true` jika user punya permission, `false` jika tidak |
| `permission` | string | Echo dari query parameter yang diminta |

> Endpoint ini menggunakan **cache yang sama** dengan `/validate` — tidak ada round-trip database tambahan jika dipanggil setelah `/validate` dalam window 60 detik.

---

## 5. Logout Terpusat

### `POST /api/v1/iam/logout`

Invalidate Sanctum token. Setelah logout, token tidak bisa digunakan lagi di endpoint manapun.

**Rate limit:** 120 request/menit per IP

**Headers:**

```http
Authorization: Bearer {sanctum_token}
X-App-Key: {api_key}
X-Timestamp: {unix_timestamp}
X-Signature: {hmac_sha256}
```

**Response 200:**

```json
{
  "message": "Token invalidated"
}
```

> Setelah memanggil endpoint ini, aplikasi klien wajib menghapus token dari session server-side dan redirect user ke halaman login.

---

## Ringkasan Endpoint

| Method | Endpoint | Auth | Rate Limit |
|--------|----------|------|------------|
| `GET` | `/sso/login` | — (web route) | — |
| `POST` | `/api/v1/iam/exchange-code` | HMAC only | 10/menit |
| `GET` | `/api/v1/iam/validate` | Bearer + HMAC | 120/menit |
| `GET` | `/api/v1/iam/check` | Bearer + HMAC | 120/menit |
| `POST` | `/api/v1/iam/logout` | Bearer + HMAC | 120/menit |
