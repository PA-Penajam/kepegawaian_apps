# Fase 2: Kepatuhan & Kualitas Kode — Design Doc

**Tanggal:** 2026-04-17
**Status:** Approved
**Scope:** 3 item improvement dari roadmap Fase 2
**Konteks:** Solo developer, dikerjakan sekuensial. Fase 1 sudah selesai.

---

## Prinsip Desain

- **Kepatuhan audit** — sistem kepegawaian pemerintahan wajib mencatat siapa mengubah apa dan kapan
- **Konsistensi kode** — FormRequest dipakai di semua controller, bukan hanya Kepegawaian
- **YAGNI** — tidak ada restore button otomatis; old values di activity log cukup untuk restore manual

---

## Item 2.1 — Audit Trail + Slow Query Logger

### Latar Belakang

Tidak ada pencatatan perubahan data saat ini. Kritis untuk sistem kepegawaian pemerintahan (SPBE/BKD). Selain itu, tidak ada mekanisme untuk mendeteksi query lambat di production.

### Komponen A: Activity Log (spatie/laravel-activitylog)

**Dependency baru:** `spatie/laravel-activitylog` (via composer)

**Model yang di-log (13 model):**

| Kategori | Model |
|---|---|
| Data Kepegawaian | `Pegawai`, `RiwayatPangkat`, `RiwayatJabatan`, `RiwayatPendidikan`, `RiwayatDiklat`, `Keluarga`, `DokumenPegawai`, `HukumanDisiplin`, `Penghargaan` |
| IAM | `IamApplication`, `IamRole`, `IamPermission`, `IamUserRole` |

**Konfigurasi trait per model:**
```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnlyDirty()        // hanya field yang berubah
        ->dontSubmitEmptyLogs() // skip jika tidak ada perubahan
        ->logFillable();        // log semua fillable attributes
}
```

**Storage:** Tabel `activity_log` (di-publish via migration spatie).

**Data per log entry:**
- `log_name`: nama kategori ('kepegawaian' atau 'iam')
- `description`: 'created' | 'updated' | 'deleted'
- `subject_type` + `subject_id`: model dan ID yang diubah
- `causer_type` + `causer_id`: user yang melakukan perubahan
- `properties->old`: nilai sebelum perubahan
- `properties->new`: nilai sesudah perubahan
- `created_at`: timestamp

### Komponen B: Slow Query Logger

**Implementasi:** `DB::listen()` di `AppServiceProvider::boot()`

**Threshold:** Query >500ms dicatat ke Laravel log (channel `daily`).

**Format log:**
```
[SLOW QUERY] 523ms | select * from `pegawai` where ... | bindings: [...]
```

**On/off switch:** Hanya aktif jika `APP_LOG_SLOW_QUERIES=true` di `.env` (default: false di local, true di production).

### Halaman Admin `/activity-log`

**Route:** `GET /activity-log` — dijaga middleware `role:admin`

**Controller:** `app/Http/Controllers/ActivityLogController.php` (baru)

**Frontend:** `resources/js/pages/activity-log/index.tsx` (baru)

**Tampilan tabel:**

| Kolom | Keterangan |
|---|---|
| Waktu | `created_at` (format: d M Y H:i) |
| Oleh | Nama causer (pegawai yang login) |
| Aksi | Badge: created / updated / deleted |
| Model | Nama model yang diubah (e.g., "Pegawai") |
| Record | Subject ID atau nama jika tersedia |
| Perubahan | Diff old → new per field (hanya saat updated) |

**Filter tersedia:**
- Model type (dropdown: semua model yang di-log)
- Causer (text search nama)
- Rentang tanggal (date range picker)

**Pagination:** 20 record per halaman.

### Data Flow

```
User action → Controller → Model::save()
    → LogsActivity trait intercept (observer)
    → Simpan ke tabel activity_log
    → properties: { old: {...}, new: {...} }

Admin buka /activity-log
    → ActivityLogController::index()
    → Query activity_log dengan filter
    → Render tabel dengan diff
```

### Testing

- Log ter-create saat `Pegawai::update()` dipanggil
- `properties->old` berisi nilai sebelumnya
- `properties->new` berisi nilai sesudahnya
- Log tidak ter-create jika tidak ada field yang berubah (`dontSubmitEmptyLogs`)
- Halaman `/activity-log` hanya bisa diakses admin
- Operator dan viewer mendapat 403

---

## Item 2.2 — FormRequest Consistency di IAM Controllers

### Latar Belakang

`AplikasiController` dan `UserAksesController` menggunakan inline `$request->validate()`. Semua controller Kepegawaian dan Referensi sudah pakai FormRequest — IAM harus konsisten.

### File Baru

```
app/Http/Requests/Iam/
├── StoreAplikasiRequest.php
├── UpdateAplikasiRequest.php
└── StoreUserAksesRequest.php
```

**Authorization pattern** (konsisten dengan existing FormRequest):
```php
public function authorize(): bool
{
    return $this->user()?->hasRole(['admin', 'operator']) ?? false;
}
```

**Catatan:** `AplikasiController` memiliki guard tambahan `abort_if($aplikasi->is_system, 403)` yang tetap dipertahankan di controller — bukan dipindah ke FormRequest, karena membutuhkan akses ke route model binding `$aplikasi`.

### Perubahan Controller

| Controller | Sebelum | Sesudah |
|---|---|---|
| `AplikasiController::store()` | `$request->validate([...])` | `StoreAplikasiRequest $request` |
| `AplikasiController::update()` | `$request->validate([...])` | `UpdateAplikasiRequest $request` |
| `UserAksesController::store()` | `$request->validate([...])` | `StoreUserAksesRequest $request` |

`UserAksesController::destroy()` tidak memerlukan FormRequest (tidak ada input yang divalidasi).

### Testing

- Validasi rules tetap identik setelah refactor
- `authorize()` menolak viewer (403)
- Tidak ada regresi pada existing test IAM

---

## Item 2.3 — Fix Duplikasi Query di PegawaiApiController

### Latar Belakang

`PegawaiApiController::search()` menjalankan dua query identis: satu untuk data (`->get()`), satu untuk total count (`->count()`). Ini N+1 level controller.

### Perubahan

**File:** `app/Http/Controllers/Api/PegawaiApiController.php`

**Sebelum:**
```php
$query = Pegawai::with([...])->when(...)->limit(20)->get();
$total = Pegawai::when(...)->count(); // query kedua identis
return response()->json(['data' => ..., 'meta' => ['total' => $total]]);
```

**Sesudah:**
```php
$result = Pegawai::with([...])->when(...)->paginate(20);
return response()->json([
    'data' => PegawaiApiResource::collection($result),
    'meta' => ['total' => $result->total(), 'per_page' => 20],
]);
```

**Response format tidak berubah** — consumer API (`attendance-qr-system`) tidak perlu update.

### Testing

- Verifikasi query count berkurang dari 2 menjadi 1 (`DB::getQueryLog`)
- Response shape `data` + `meta.total` + `meta.per_page` tetap sama
- Existing test `PegawaiApiTest.php` harus tetap pass tanpa modifikasi

---

## Urutan Pengerjaan

```
Task 1: Item 2.3 (paling simpel, pemanasan)
Task 2: Item 2.2 (FormRequest, tidak ada dependency)
Task 3: Item 2.1 (paling kompleks, dikerjakan terakhir)
```

Item 2.3 dan 2.2 bisa dikerjakan dalam urutan apapun karena independen. Item 2.1 dikerjakan terakhir karena scope terbesar.

---

## Ringkasan File yang Diubah/Dibuat

| Item | Tipe | File |
|---|---|---|
| 2.1 | Baru | `app/Http/Controllers/ActivityLogController.php` |
| 2.1 | Baru | `resources/js/pages/activity-log/index.tsx` |
| 2.1 | Modifikasi | `app/Providers/AppServiceProvider.php` |
| 2.1 | Modifikasi | 13 Model files |
| 2.1 | Baru | `tests/Feature/ActivityLogTest.php` |
| 2.2 | Baru | `app/Http/Requests/Iam/StoreAplikasiRequest.php` |
| 2.2 | Baru | `app/Http/Requests/Iam/UpdateAplikasiRequest.php` |
| 2.2 | Baru | `app/Http/Requests/Iam/StoreUserAksesRequest.php` |
| 2.2 | Modifikasi | `app/Http/Controllers/Iam/AplikasiController.php` |
| 2.2 | Modifikasi | `app/Http/Controllers/Iam/UserAksesController.php` |
| 2.3 | Modifikasi | `app/Http/Controllers/Api/PegawaiApiController.php` |
| 2.3 | Modifikasi | `tests/Feature/Api/PegawaiApiTest.php` |
