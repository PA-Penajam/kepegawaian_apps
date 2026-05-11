# Learnings — sikep-p1-administrasi

## [2026-05-11] Session ses_1e7c87b4fffeYQ7x92284dD4WI — Plan Start

### Project Conventions
- ULID dipakai di semua model (bukan bigIncrements)
- SoftDeletes dipakai di model utama
- State machine: `spatie/laravel-model-states` v2.7, suffix `State` di nama class, `$name` UPPERCASE
- Activity log: `spatie/laravel-activitylog` v5, `getActivitylogOptions()` di setiap model
- IAM: table `iam_permissions` (bukan `ref_permissions` — sudah di-drop), kolom `slug` (bukan `code`)
- Permission middleware: `iam.permission` (alias di bootstrap/app.php)
- Laravel 11/12 streamlined: tidak ada `EventServiceProvider.php`, event di `AppServiceProvider::boot()`
- Pest 4 untuk testing, `php artisan test --compact`
- Pint untuk formatting: `vendor/bin/pint --dirty --format agent`
- Inertia v2 + React 19 untuk frontend
- Tailwind v4 untuk styling
- Wayfinder untuk route functions di frontend

### Key File Locations
- IAM seeder pattern: `database/seeders/IamSeeder.php`
- Cuti pattern (blueprint): `app/Models/Cuti/`, `app/States/Cuti/`, `app/Http/Controllers/Cuti/`
- KP monitoring service: `app/Services/KenaikanPangkatMonitoringService.php`
- KGB pattern (twin): `app/Services/KgbMonitoringService.php`, `app/Notifications/KgbJatuhTempoNotification.php`
- Config: `config/sikep.php` (akan dibuat di T4)
- AppServiceProvider: `app/Providers/AppServiceProvider.php`

### Critical Guardrails
- JANGAN modifikasi schema `pegawai`, `riwayat_pangkat`, `ref_pangkat`, atau table Cuti
- JANGAN buat `EventServiceProvider.php` — tidak ada di Laravel 11/12
- JANGAN pakai `DB::raw()` kecuali sudah ada di service existing
- JANGAN pakai kolom `code` di iam_permissions — pakai `slug`
- TDD wajib: RED-GREEN-REFACTOR

## [2026-05-12] KP monitoring bulanan
- `KenaikanPangkatMonitoringService` sekarang pakai filter `periode_bulan`/`periode_tahun` numerik dan tetap format output `{NamaBulan} {YYYY}`.
- `status_kepegawaian = 'pppk'` wajib dikecualikan dari monitoring KP karena scope P1 hanya PNS.
- Hukuman disiplin aktif tersedia via relasi `Pegawai::hukumanDisiplin()` dan scope `HukumanDisiplin::aktif()`.

## [2026-05-12] Berkas checklist polimorfik
- Schema aktual checklist memakai `jenis` untuk domain template dan `wajib` untuk flag item wajib; service/test menyesuaikan schema ini.
- `validated_by` pada `berkas_checklist_submission_items` harus refer ke `pegawai`, bukan `users`, karena auth provider aplikasi memakai `App\Models\Pegawai` dan tidak ada tabel `users`.
- Test upload checklist memakai `Storage::fake('local')` + `UploadedFile::fake()->create()` lalu assert via `Storage::disk('local')->exists($path)`.
- Monitoring KP frontend kini memakai query `bulan`/`tahun`; controller tetap menerima alias legacy `periode_bulan`/`periode_tahun` untuk export/backward compatibility.
- `php artisan test --compact --filter=MonitoringKenaikanPangkat` hanya menangkap test yang namanya memuat `MonitoringKenaikanPangkat`; nama test controller KP perlu prefix tersebut.

## [2026-05-12] Surat pengantar KP PDF
- Generator PDF KP memakai `Spatie\LaravelPdf\Facades\Pdf::view(...)->withBrowsershot(...)->save($absolutePath)`; lingkungan Linux ini perlu arg Chromium `--no-sandbox`, `--disable-setuid-sandbox`, dan `--disable-dev-shm-usage`.
- Path file surat pengantar KP: `storage/app/usulan-kp/surat-pengantar/{usulan_id}.pdf`; metadata disimpan ke `usulan_kp_pdf` dengan `jenis_pdf = surat_pengantar`.
- Nomor surat KP memakai `NomorSuratService::reserve('KP.01.1', bulan, tahun)` lalu `confirm()` setelah PDF berhasil tersimpan.

## [2026-05-12] Usulan Kenaikan Pangkat model/state
- Model KP memakai `spatie/laravel-model-states` dengan 11 concrete state suffix `State` dan `$name` UPPERCASE.
- `users` table sudah tidak ada setelah migrasi auth ke `pegawai`; FK audit KP perlu mengarah ke `pegawai` agar SQLite test tidak gagal `no such table: main.users`.
- Placeholder nomor surat migrations perlu kolom sequence/reservation nyata karena test PDF KP mengakses `klasifikasi`, `tahun`, `next_number`, dan `nomor_lengkap`.

## [2026-05-12] UsulanKenaikanPangkatService
- Service KP memakai `Pegawai` eksplisit sebagai actor untuk semua transisi; tidak memakai `auth()`.
- Transisi service wajib buat `state_history` dan `approver_history` dalam transaksi yang sama sebelum `state->transitionTo()`.
- Upload SK final menyimpan file ke disk `local` path `usulan-kp/sk/{usulan_id}.pdf` dan metadata SK tetap berasal dari input user.

## [2026-05-12] Integrasi checklist KP ke usulan
- `UsulanKenaikanPangkatService::createDraft()` auto-attach template dari `config('sikep.kp.checklist_template_kode')`, bukan hardcode.
- `submit()` memakai `ChecklistBerkasService::isComplete()` dan melempar `BerkasBelumLengkapException` jika checklist null/incomplete.
- `ChecklistBerkasService::recalculatePersentase()` dispatch `ChecklistKelengkapanBerubah` hanya saat `status_kelengkapan` berubah.

## [2026-05-12] Admin SK KP controller
- Test controller Inertia tanpa route production memakai route sementara di test; gunakan `actingAs($user)->withoutVite()->get(...)` agar Vite manifest tidak dibutuhkan.
- Upload SK final web controller cukup delegasi ke `UsulanKenaikanPangkatService::uploadSkFinal()`; test mock service agar tidak mengulang business logic service.
- File download lokal aman untuk Intelephense via `Response::download(Storage::disk('local')->path($path), $filename)`.

## [2026-05-12] UsulanKenaikanPangkatController web
- Controller web KP memakai route name `usulan-kenaikan-pangkat.*`; test task ini mendaftarkan route sementara `_test/usulan-kenaikan-pangkat` tanpa menyentuh `routes/web.php` karena route ditangani task lain.
- `StoreUsulanKenaikanPangkatRequest` mensyaratkan `pegawai_id` UUID, jadi test store perlu membuat `Pegawai` dengan UUID eksplisit bila factory default menghasilkan ULID.
- Inertia feature test untuk page yang belum punya entry Vite perlu `withoutVite()` agar fokus tetap pada response Inertia controller.
