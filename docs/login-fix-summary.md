# Fix Login Validation untuk Status Pegawai

## 📋 Ringkasan Masalah

Aplikasi memiliki **DUA MEKANISME LOGIN** yang berbeda:

| Route | Metode | Validasi Status Pegawai |
|-------|--------|------------------------|
| `/login` | Laravel Fortify (NIP + Password) | ❌ TIDAK ADA (sebelum fix) |
| `/keycloak/login` | Keycloak SSO (OAuth2/OIDC) | ✅ Ada (harus "aktif") |

### Root Cause dari Log Error

Dari log Laravel ditemukan error berulang:

```log
Keycloak callback: Pegawai tidak aktif 
{"nip":"716335482115207726","status":"meninggal"}
```

User mencoba login via Keycloak SSO dengan NIP yang status-nya `"meninggal"`.

### Validasi Existing di KeycloakAuthController.php

```php
// Line 155 - KeycloakAuthController.php
if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
    Log::warning('Keycloak callback: Pegawai tidak aktif', [
        'nip' => $claims->nip,
        'status' => $pegawai->status_pegawai->value ?? 'unknown',
        'ip' => $request->ip(),
    ]);

    return redirect()->route('keycloak.login')
        ->with('error', 'Akun Pegawai tidak aktif. Hubungi administrator untuk informasi lebih lanjut.');
}
```

**Problem**: Validasi ini HANYA ada di Keycloak, TIDAK ada di Laravel Fortify login!

---

## 🔧 Solusi yang Diimplementasikan

### 1. Membuat Middleware `ValidatePegawaiStatus`

**File**: `/app/Http/Middleware/ValidatePegawaiStatus.php`

Middleware ini menangani validasi status Pegawai untuk:
- Laravel Fortify login (`GET /login`, `POST /login`)
- Keycloak SSO callback (`GET /keycloak/callback`)

**Fitur**:
- Skip validation jika route bukan login-related
- Cek NIP dari session (Keycloak) atau request body (Fortify)
- Redirect dengan pesan error yang informatif dan user-friendly
- Menampilkan label status pegawai (e.g., "Meninggal", "Pensiun", dll)

### 2. Mendaftar Middleware di Pipeline Fortify

**File**: `/config/fortify.php`

```php
'pipelines' => [
    'login' => [
        \App\Http\Middleware\ValidatePegawaiStatus::class,
    ],
],
```

### 3. Register Alias Middleware

**File**: `/bootstrap/app.php`

```php
$middleware->alias([
    // ... existing middleware ...
    'pegawai.status' => ValidatePegawaiStatus::class,
]);
```

---

## ✅ Hasil Setelah Fix

Sekarang kedua mekanisme login akan memvalidasi status Pegawai:

### Scenario 1: Login Via Fortify (`/login`)

**Input**:
- NIP: `716335482115207726`
- Password: (correct)
- Status Pegawai: `meninggal`

**Expected Result**:
```
Error: Akun Pegawai tidak aktif (Meninggal). Hubungi administrator untuk informasi lebih lanjut.
Redirect: Back to login page with error message
```

### Scenario 2: Login Via Keycloak (`/keycloak/login`)

**Flow**:
1. User redirect ke Keycloak → authenticate
2. Callback ke `/keycloak/callback` → token validated
3. NIP ditemukan di database dengan status `meninggal`
4. Middleware `ValidatePegawaiStatus` triggered
5. **Result**: Error message ditampilkan

---

## 📊 Enum Status Pegawai

Dari `/app/Enums/StatusPegawai.php`:

```php
enum StatusPegawai: string
{
    case Aktif = 'aktif';           // ✅ ALLOW LOGIN
    case MutasiKeluar = 'mutasi_keluar';  // ❌ BLOCKED
    case Pensiun = 'pensiun';       // ❌ BLOCKED
    case Meninggal = 'meninggal';   // ❌ BLOCKED
    case Diberhentikan = 'diberhentikan'; // ❌ BLOCKED
}
```

---

## 🧪 Testing

Untuk test fix ini:

### Test Case 1: User Aktif (Success)
```bash
curl -X POST http://kepegawaian-apps.test/login \
  -H "Content-Type: application/json" \
  -d '{"nip":"197503141996031002","password":"password","remember":false}'
```

**Expected**: Authentication proceeds (or wrong password error)

### Test Case 2: User Tidak Aktif (Blocked)
```bash
curl -X POST http://kepegawaian-apps.test/login \
  -H "Content-Type: application/json" \
  -d '{"nip":"716335482115207726","password":"password","remember":false}'
```

**Expected**: Error message about inactive status

### Test Case 3: Invalid NIP
```bash
curl -X POST http://kepegawaian-apps.test/login \
  -H "Content-Type: application/json" \
  -d '{"nip":"999999999999999999","password":"password","remember":false}'
```

**Expected**: "The provided credentials are incorrect."

---

## 🎯 Best Practices Implementasi

### 1. Single Responsibility Principle
- Setiap kelas hanya punya satu tanggung jawab
- Middleware khusus untuk validasi status
- Controller fokus pada autentikasi logic

### 2. Early Validation Pattern
- Validasi dilakukan SEBELUM proses auth utama
- Memberikan feedback lebih cepat ke user
- Menghindari wasteful processing

### 3. Pipeline Architecture
- Menggunakan Fortify pipeline pattern
- Mudah untuk menambahkan custom validation lain
- Clean separation of concerns

### 4. User-Friendly Error Messages
- Pesan dalam Bahasa Indonesia
- Menyebutkan status spesifik (Meninggal, Pensiun, dll)
- Memberikan instruksi jelas (Hubungi admin)

---

## 📝 Files Modified

1. ✅ `/app/Http/Middleware/ValidatePegawaiStatus.php` (NEW)
2. ✅ `/bootstrap/app.php` (Added import & alias)
3. ✅ `/config/fortify.php` (Added to pipelines)

---

## 🔐 Security Considerations

1. **Session Hijacking Prevention**: Session regeneration setelah login successful
2. **CSRF Protection**: Protected by Laravel's built-in CSRF middleware
3. **Rate Limiting**: 5 requests per minute (default Fortify config)
4. **Two-Factor Authentication**: Enabled (optional but recommended)

---

## 🚀 Deployment Steps

1. Clear config cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

2. Deploy changes to production

3. Verify logs untuk memastikan no new errors

---

## 📞 Maintenance Notes

Jika perlu update status Pegawai:
- Gunakan Filament Admin Panel atau artisan command
- Hindari update manual langsung ke database
- Pastikan ada audit trail untuk perubahan status

Untuk menambah mekanisme login baru:
- Copy pattern dari middleware `ValidatePegawaiStatus`
- Sesuaikan routing dan session keys
- Add ke pipeline yang sesuai

---

**Created**: 2026-08-19  
**Author**: moohard  
**Context**: Systematic Debugging using Superpowers methodology
