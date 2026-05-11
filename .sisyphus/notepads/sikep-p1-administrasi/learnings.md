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

## [2026-05-12] Surat pengantar KP PDF
- Generator PDF KP memakai `Spatie\LaravelPdf\Facades\Pdf::view(...)->withBrowsershot(...)->save($absolutePath)`; lingkungan Linux ini perlu arg Chromium `--no-sandbox`, `--disable-setuid-sandbox`, dan `--disable-dev-shm-usage`.
- Path file surat pengantar KP: `storage/app/usulan-kp/surat-pengantar/{usulan_id}.pdf`; metadata disimpan ke `usulan_kp_pdf` dengan `jenis_pdf = surat_pengantar`.
- Nomor surat KP memakai `NomorSuratService::reserve('KP.01.1', bulan, tahun)` lalu `confirm()` setelah PDF berhasil tersimpan.

## [2026-05-12] Usulan Kenaikan Pangkat model/state
- Model KP memakai `spatie/laravel-model-states` dengan 11 concrete state suffix `State` dan `$name` UPPERCASE.
- `users` table sudah tidak ada setelah migrasi auth ke `pegawai`; FK audit KP perlu mengarah ke `pegawai` agar SQLite test tidak gagal `no such table: main.users`.
- Placeholder nomor surat migrations perlu kolom sequence/reservation nyata karena test PDF KP mengakses `klasifikasi`, `tahun`, `next_number`, dan `nomor_lengkap`.
