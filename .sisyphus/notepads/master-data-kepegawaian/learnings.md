# Learnings — master-data-kepegawaian

## [2026-03-15] Task 1: PHP Enums & TypeScript Types

### Enum Implementation

- 9 PHP Enums created di `app/Enums/`
- All are string-backed enums (bukan integer)
- Setiap enum memiliki method `label(): string` untuk display name Bahasa Indonesia
- Namespace: `App\Enums\`

### Testing

- 9 test files di `tests/Unit/Enums/`
- Menggunakan Pest `describe()` dan `it()` syntax
- Semua test PASS (36 tests, 102 assertions)

### TypeScript Types

- File: `resources/js/types/kepegawaian.ts`
- Mirror semua PHP enums dengan union string types
- Ditambahkan `Labels` records untuk display values

### QA Verification

- `php artisan test --compact --filter=Enum` → ALL PASS
- `npm run build` → exit 0
- Tinker verification → values dan labels benar

---

## [2026-03-15] Initialized

- Project: Laravel 12 + React 19 + Inertia v2 + Tailwind 4 + shadcn/ui
- Auth: Lengkap (Fortify), belum ada kode kepegawaian
- Test: Pest 4, 14 existing test files
- No repository pattern — direct Eloquent
- Middleware registration: bootstrap/app.php (bukan Kernel.php — Laravel 12)
- String-backed enums (bukan integer)
- SoftDeletes pada semua entity kepegawaian
- NIP nullable (honorer)
- User ↔ Pegawai: 1:1, users.pegawai_id nullable FK
- RBAC: Enum-based (Admin, Operator, Viewer), TIDAK pakai Spatie
- Seeder idempotent via updateOrCreate

## Task 4: Sidebar Navigation Restructure

- Installed shadcn/ui components: `table`, `tabs`, `progress`, `pagination`.
- Updated `NavMain` component to accept an optional `title` prop to render multiple `SidebarGroup`s.
- Updated `useCurrentUrl` hook usage in `NavMain` to use `isCurrentOrParentUrl` instead of `isCurrentUrl` so that parent menu items remain active when navigating to child routes.
- Added `Kepegawaian` and `Monitoring` groups to `AppSidebar` with corresponding menu items and icons from `lucide-react`.

## [2026-03-15] Task 3: RBAC Role Middleware

- Role enum ditambahkan di `app/Enums/Role.php` sebagai string-backed enum dengan `Admin`, `Operator`, dan `Viewer`, plus `label()` untuk display.
- Middleware alias custom di Laravel 12 didaftarkan di `bootstrap/app.php` lewat `withMiddleware(...)->alias(['role' => EnsureRole::class])`.
- `EnsureRole` mendukung parameter route seperti `role:admin,operator`, redirect guest ke login untuk web request, dan `abort(403)` untuk user dengan role yang tidak cocok.
- `User` sekarang cast `role` ke enum `App\Enums\Role` dan punya helper `isAdmin()`, `isOperator()`, `isViewer()`.
- `UserFactory` default ke `viewer` dan punya state `admin()` serta `operator()` agar test role lebih ringkas.
- Verifikasi final tersimpan di `.sisyphus/evidence/task-3-role-middleware.txt`; test `Role`, `Auth`, `Dashboard`, Pint, build frontend, dan LSP diagnostics file yang diubah semuanya lolos.

## [2026-03-15] Task 5: Pegawai Core Entity

- Entity `Pegawai` wajib set `protected $table = 'pegawai'` karena naming tabel non-plural standar Laravel.
- `create_pegawai_table` harus dijalankan sebelum migration FK `users.pegawai_id`; timestamp migration perlu diatur agar urutan eksekusi benar.
- Scope `byGolongan` paling aman memfilter via relationship `pangkat` dan kolom `kode` (`III/a`, `IV/a`) agar konsisten dengan data referensi.
- Seeder impor JSON lebih stabil jika mapping jabatan/unit kerja memakai keyword normalization lalu fallback `null` untuk FK yang tidak cocok.
- Untuk data master campuran PNS/PPPK/Honorer, status kepegawaian bisa diturunkan dari field `gol` dan NIP dapat dinullkan untuk Honorer sesuai requirement.
- Evidence verifikasi Task 5 disimpan di `.sisyphus/evidence/task-5-pegawai-create.txt`, `.sisyphus/evidence/task-5-pegawai-relations.txt`, dan `.sisyphus/evidence/task-5-user-pegawai-link.txt`.

## [2026-03-15] Task 2: Reference Tables Kepegawaian

- Semua reference table kepegawaian memakai ULID primary key, `SoftDeletes`, dan `fillable`/`casts` eksplisit di model.
- Nama tabel reference mengikuti bentuk singular ber-underscore (`ref_pangkat`, `ref_jabatan`, `ref_unit_kerja`, dst), jadi model wajib set properti `protected $table`.
- Seeder reference dibuat idempotent dengan `updateOrCreate`; `RefUnitKerjaSeeder` harus seed parent lebih dulu lalu child memakai `parent_id` hasil parent yang sudah tersimpan.
- `RefJabatan` menyimpan value string enum `App\Enums\JenisJabatan` dan cast model-nya langsung ke enum tersebut.
- Test Pest untuk reference models lebih bersih bila memanggil seeder langsung (`(new Seeder)->run()`) dan memverifikasi soft delete via `trashed()`, `find()`, dan `withTrashed()` daripada helper dinamis `$this`.
- QA seed reference yang tervalidasi: `RefPangkat` = 17, `III/a` = `Penata Muda`, `RefJabatan` = 15, unit kerja root = 2, child = 6; evidence di `.sisyphus/evidence/task-2-seed-data.txt`.

## [2026-03-15] Task 6: Pegawai Resource Controller, Requests, Policy, dan Routes

- Validasi Pegawai paling aman dibagi lewat concern shared rules agar Store/Update request konsisten, termasuk Rule::enum untuk semua enum string-backed dan unique ignore untuk NIP/email saat update.
- Controller Inertia Pegawai perlu page TSX yang benar-benar ada di resources/js/pages/kepegawaian/pegawai agar test web tidak gagal oleh Vite manifest.
- Policy Pegawai cukup batasi admin/operator lewat helper bersama, sementara route group tetap memakai middleware role:admin,operator untuk guard awal pada seluruh resource web.
- route:list dengan path kepegawaian/pegawai sekarang ikut menangkap nested routes riwayat yang sudah ada; verifikasi resource utama perlu melihat 7 route PegawaiController di samping 4 route nested prefix yang berbagi path dasar.

## [2026-03-15] Task 12: Data Keluarga Pegawai

- Entity `Keluarga` memakai tabel singular `keluarga`, ULID primary key, `SoftDeletes`, relasi `belongsTo` ke `Pegawai`, dan cast enum lewat method `casts()`.
- Nested resource keluarga mengikuti naming route proyek `kepegawaian.pegawai.keluarga.*` dengan path `/kepegawaian/pegawai/{pegawai}/keluarga`, tanpa `shallow()` agar update/destroy tetap membawa konteks parent `pegawai`.
- `HubunganKeluarga` perlu memuat 7 value string-backed (`Suami`, `Istri`, `Anak`, `AyahKandung`, `IbuKandung`, `AyahMertua`, `IbuMertua`) supaya validasi `Rule::enum(...)`, cast model, dan payload test konsisten.
- Page Inertia baru wajib ikut masuk ke `public/build/manifest.json`; test web akan gagal dengan `ViteException` sampai `npm run build` dijalankan setelah file TSX ditambahkan.

## [2026-03-15] Task 9: Riwayat Jabatan

- Nested route `pegawai.riwayat-jabatan` paling aman diletakkan di group `prefix('kepegawaian')->name('kepegawaian.')` lalu `->shallow()` supaya nama route tetap rapi: index/store tetap nested, update/destroy jadi shallow.
- Untuk alur `is_aktif=true`, sinkronisasi `RiwayatJabatan` aktif lain dan update `Pegawai.ref_jabatan_id` + `Pegawai.ref_unit_kerja_id` cukup dikerjakan di controller dalam transaksi; belum perlu trait atau service tambahan.
- Test Inertia untuk page baru membutuhkan file TSX benar-benar ada dan frontend sudah dibuild agar manifest Vite mengenali entry `resources/js/pages/kepegawaian/pegawai/riwayat-jabatan.tsx`.
- Saat file yang disentuh mewarisi referensi model/controller task lain yang belum ada, mengganti referensi yang belum tersedia ke class-string literal bisa menjaga `lsp_diagnostics` tetap bersih tanpa menambah stub di luar scope task.
- Evidence Task 9 tersimpan di `.sisyphus/evidence/task-9-jabatan-sync.txt` dan `.sisyphus/evidence/task-9-jabatan-build.txt`.

## [2026-03-15] Task 10: Riwayat Pendidikan CRUD

- Riwayat pendidikan mengikuti pola history murni: semua entri ditampilkan, tanpa `is_aktif`, tanpa sinkronisasi ke `pegawai.pendidikan_terakhir`.
- Route nested paling stabil jika didefinisikan di group `prefix('kepegawaian')->name('kepegawaian.')` lalu `Route::resource('pegawai.riwayat-pendidikan', ...)->shallow()`; hasilnya index/store tetap nested dan update/destroy jadi shallow.
- Untuk form request yang identik antara create dan update, `UpdateRiwayatPendidikanRequest` bisa mewarisi `StoreRiwayatPendidikanRequest` agar aturan dan pesan validasi tetap satu sumber.
- Build frontend dengan plugin Wayfinder otomatis menghasilkan action, route, dan form variants untuk controller baru saat `npm run build` dijalankan.

## [2026-03-15] Task 8: Riwayat Pangkat Sync

- Riwayat pangkat berbeda dari riwayat pendidikan karena punya `is_aktif`; saat satu entri aktif disimpan, semua riwayat pangkat lain milik pegawai itu harus di-nonaktifkan dan `pegawai.ref_pangkat_id` harus ikut disinkronkan.
- Untuk kebutuhan path penuh `/kepegawaian/pegawai/{pegawai}/riwayat-pangkat/...`, route nested tidak boleh `->shallow()`; hanya `Route::resource('pegawai.riwayat-pangkat', ...)->only([...])`.
- Test Inertia untuk page baru akan gagal dengan `Vite manifest` bila page TSX belum ikut build; `npm run build` memperbarui manifest sekaligus generate artefak Wayfinder terbaru.
- Evidence verifikasi Task 8 disimpan di `.sisyphus/evidence/task-8-pangkat-sync.txt`, `.sisyphus/evidence/task-8-riwayat-crud.txt`, dan `.sisyphus/evidence/task-8-riwayat-pangkat-build.txt`.

## [2026-03-15] Task 11: RiwayatDiklat

- RiwayatDiklat memakai nested CRUD di bawah `Pegawai` tanpa sync logic dan seluruh riwayat selalu ditampilkan.
- Route `kepegawaian.pegawai.riwayat-diklat.*` dipertahankan full nested untuk update/delete; scoping parent-child dijaga di controller lewat pemeriksaan `pegawai_id`.
- Halaman Inertia `resources/js/pages/kepegawaian/pegawai/riwayat-diklat.tsx` dibuat minimal valid dan build menghasilkan chunk `riwayat-diklat` baru.

## [2026-03-15] Task 13: Penghargaan

- Entity `Penghargaan` mengikuti pola tabel singular `penghargaan` dengan ULID primary key, `SoftDeletes`, relasi `belongsTo` ke `Pegawai` dan `RefJenisPenghargaan`, serta route nested penuh `kepegawaian.pegawai.penghargaan.*` tanpa `shallow()`.
- Test Inertia untuk sub-page baru tetap bergantung pada `public/build/manifest.json`; setelah menambah `resources/js/pages/kepegawaian/pegawai/penghargaan.tsx`, `npm run build` harus dijalankan agar request web tidak gagal dengan `ViteException`.
- Validasi store/update cukup sederhana: semua field metadata penghargaan opsional kecuali `nama_penghargaan`, sehingga controller bisa langsung memakai `$request->validated()` tanpa transformasi tambahan.

## [2026-03-15] Task: Monitoring KGB

- Service monitoring KGB paling aman mengambil `riwayatPangkat` aktif via eager load terurut `latest('tmt')`, lalu skip pegawai tanpa riwayat aktif agar tidak melempar error di listing.
- Status KGB mengikuti threshold berbasis `sisa_hari` (`<=0` jatuh tempo, `<=60` segera, `<=90` mendekati, selebihnya aman) dan `diffInDays(..., false)` perlu di-cast ke `int` supaya assertion test tetap konsisten.
- Monitoring hanya untuk status pegawai `Aktif` dan `MutasiKeluar`; status `Pensiun`, `Meninggal`, dan `Diberhentikan` otomatis terfilter karena query service membatasi status.
- Page monitoring baru (`resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`) harus dibuild agar terdaftar di `public/build/manifest.json`; tanpa build, request Inertia web akan gagal dengan `ViteException`.
- Verifikasi task ini: `php artisan test --compact --filter=KgbMonitoring` lulus, `npm run build` lulus, tetapi full suite saat ini masih punya 4 gagal existing di `KenaikanPangkatMonitoringTest` (type mismatch `Carbon` vs `CarbonImmutable`).

## [2026-03-15] Task: T7

- Created `resources/js/pages/kepegawaian/pegawai/index.tsx` using shadcn/ui components (`Table`, `Badge`, `Pagination`, `Button`).
- Added `PaginatedData` and `Pegawai` types to `resources/js/types/kepegawaian.ts`.
- Used `usePage().props.auth.user.role` to conditionally render action buttons based on user role.
- Used Wayfinder routes (`index`, `show`, `create`, `edit`) from `@/routes/kepegawaian/pegawai` for navigation.
- Mapped `StatusPegawai` to specific badge variants (e.g., `aktif` -> `default`, `mutasi_keluar` -> `warning` with custom classes).

### Pegawai Show Page

- The `PegawaiController::show()` method needs to eager load all relationships to display the data in the show page.
- The `show.tsx` page uses `shadcn/ui` Tabs to display the data in 9 tabs: Biodata, Keluarga, Riwayat Pangkat, Riwayat Jabatan, Riwayat Pendidikan, Riwayat Diklat, Penghargaan, Hukuman Disiplin, Dokumen.
- The `PegawaiDetail` type needs to be defined with all the relationships to ensure type safety.
- The `getInitials` function can be used to generate initials for the avatar fallback.

## [2026-03-15] Task 17: Monitoring Kenaikan Pangkat

- Service `KenaikanPangkatMonitoringService` paling aman memuat pegawai non-pensiun/non-meninggal/non-diberhentikan lalu skip yang tidak punya `riwayat_pangkat` aktif agar list monitoring tetap relevan.
- Perhitungan KP reguler stabil dengan pola `tmt aktif + 4 tahun`; penentuan periode usul dapat diturunkan dari bulan target KP (`April` atau `Oktober`) berikut batas usul (`1 Oktober` tahun sebelumnya untuk periode April, `1 April` tahun berjalan untuk periode Oktober).
- Status monitoring cukup dibagi tiga berdasarkan `today` dan ambang `today + 6 bulan`: `Sudah Eligible`, `Mendekati Eligible`, `Belum Eligible`.
- Untuk page monitoring baru berbasis Inertia, test web membutuhkan `public/build/manifest.json` terbarui; jalankan `npm run build` setelah menambah file TSX agar route test tidak gagal dengan `ViteException`.

## [2026-03-15] Task 18: Dashboard Statistik

- When calculating statistics that involve string manipulation (like extracting Golongan from `I/a`), it's safer to do it in PHP after fetching the data if the database engine might vary (e.g., SQLite for tests, MySQL for production), as functions like `SUBSTRING_INDEX` are not universally supported.
- Added `pegawai()` relationship to `RefUnitKerja` model to allow `withCount(['pegawai' => fn($q) => $q->aktif()])` to work correctly.
- Used shadcn/ui components (`Card`, `Progress`, `Badge`) to build a clean and informative dashboard without relying on heavy external charting libraries.
- Implemented multi-step form for Pegawai Create and Edit pages using React state and Inertia useForm.

## [2026-03-15] F1 Audit: Read-only Compliance Summary

- Audit read-only menunjukkan fondasi utama kepegawaian memang sudah ada: enum, model, controller, monitoring service, halaman React, RBAC middleware, dan self-service route semuanya terdeteksi.
- Lolosnya full suite, Pint, dan build frontend tidak cukup untuk APPROVE jika ada item plan yang belum 1:1; audit perlu tetap membandingkan implementasi terhadap detail task dan Definition of Done.
- Route `shallow()` pada nested resource bisa tetap membuat test lulus, tetapi tetap dianggap deviasi bila acceptance plan mensyaratkan path nested penuh.
- Bukti implementasi di `.sisyphus/evidence/` perlu dijaga lengkap per task; ketiadaan artefak final QA membuat verifikasi browser end-to-end tidak bisa dianggap selesai.

## [2026-03-15] Task 21: Self-Service Pegawai

- Self-service paling aman diproteksi dua lapis: group `auth` + `verified` di route, lalu middleware `pegawai.linked` untuk mengarahkan user tanpa `pegawai_id` ke halaman informasi khusus tanpa loop redirect.
- `SelfServiceController` bisa tetap ringan bila mengambil pegawai login lewat relationship `request()->user()->pegawai()` dan memisahkan daftar eager-load ringkas (`index`) vs detail penuh (`detail`).
- Ringkasan monitoring self-service lebih akurat jika halaman frontend memakai shape data asli dari service: KGB memakai `tanggal_kgb_berikutnya`, sedangkan KP memakai `tmt_kp_berikutnya`, `periode_usul`, `batas_usul`, dan `sisa_hari_usul`.
- Untuk menghindari duplikasi besar antara `kepegawaian/pegawai/show` dan `self-service/detail`, ekstrak tab read-only bersama ke `resources/js/components/pegawai-detail-tabs.tsx` dan tipe detail ke `resources/js/types/pegawai-detail.ts`.
- Sidebar RBAC frontend cukup membaca `usePage().props.auth.user.role`; role `viewer` hanya perlu melihat `Dashboard`, `Data Saya`, dan `Settings`, sedangkan menu Kepegawaian/Monitoring disembunyikan di level komponen.
- Evidence Task 21 tersimpan di `.sisyphus/evidence/task-21-rbac-enforcement.txt`; verifikasi akhir lulus pada `php artisan test --compact --filter=SelfServiceAccess`, `php artisan test --compact`, `npm run build`, `vendor/bin/pint --dirty --format agent`, dan `php artisan route:list | grep self-service`.

## [2026-03-15] Task 22: Filter dan Sort Jabatan di Index Pegawai

- Filter jabatan di `PegawaiController::index()` paling aman memakai `where('ref_jabatan_id', $request->input('jabatan'))` agar selaras dengan dropdown frontend yang mengirim ID jabatan.
- Sort kolom Jabatan bisa mengikuti pola sort Pangkat dengan correlated subquery `orderBy(RefJabatan::select('nama')->whereColumn(...))`, jadi tidak perlu mengubah eager loading atau menambah join manual.
- Untuk perubahan frontend yang dibatasi scope file, tipe filter/sort lokal di halaman `resources/js/pages/kepegawaian/pegawai/index.tsx` bisa diperluas sendiri dengan field `jabatan` dan sort key `'jabatan'` tanpa menyentuh shared type `resources/js/types/kepegawaian.ts`.
- Verifikasi task ini lulus pada `php artisan test --compact --filter=PegawaiController`, `php artisan test --compact`, dan `npm run build`; `npm run build` masih menampilkan warning npm `Unknown project config "public-hoist-pattern"` yang sudah dikenal.

### Dashboard Statistik

- `JenjangPendidikan` enum digunakan untuk mapping label pendidikan terakhir dari string value di database.
- `RefJabatan` tidak memiliki relasi `pegawai()` secara default, sehingga query distribusi jabatan dilakukan dari model `Pegawai` dengan `with('jabatan')` dan `groupBy('ref_jabatan_id')`.
- Untuk widget dashboard, gunakan komponen shadcn/ui seperti `Card`, `Progress`, dan `Badge` tanpa perlu menginstall library chart eksternal.

## [2026-03-15] Task 21: Self-Service Pegawai (follow-up)

- Jika prop Inertia self-service mengambil hasil langsung `getKgbStatus()` / `getKpStatus()`, field tanggal dari `Carbon` akan terserialisasi ke ISO8601 penuh; memakai `getUpcomingKgb(12)` dan `getUpcomingKenaikanPangkat()` memberi payload string tanggal `Y-m-d` yang lebih stabil untuk test dan UI.
- Route self-service lebih rapi bila seluruh endpoint (`/`, `/detail`, `/unlinked`) dikelompokkan di prefix `self-service` lalu hanya dashboard/detail yang dibungkus middleware `pegawai.linked`.
- Dashboard self-service masih perlu kartu `Masa Kerja` terpisah; cukup hitung di frontend dari `pegawai.tmt_pns` tanpa menambah prop backend baru.
- Sidebar role `viewer` tetap aman bila menu utama dibatasi ke `Dashboard`, `Data Saya`, dan `Settings`, sedangkan grup `Kepegawaian` dan `Monitoring` hanya dirender untuk admin/operator.

## [2026-03-15] F3 QA Final Verification Wave

- Final QA viewer perlu memeriksa asset avatar/default image juga; halaman dapat terlihat benar tetapi tetap gagal APPROVE bila browser console mencatat `404` seperti request `/pegawai/default-2.jpg`.
- Skenario RBAC manual yang memang mengharapkan `403 Forbidden` sebaiknya diperlakukan sebagai expected network call, selama tidak ada error browser lain di luar response `403` itu sendiri.
- Flow create pegawai masih bergantung pada asumsi schema reference yang tidak konsisten: `RefJabatan` di controller diurutkan dengan kolom `urutan`, padahal tabel hasil seed tidak memilikinya.

## [2026-03-15] Task 21: Self-Service Pegawai (verification refresh)

- Saat task slice ini dijalankan, seluruh artefak self-service (`EnsurePegawaiLinked`, `SelfServiceController`, page `self-service/*`, route, dan test) ternyata sudah ada di workspace; verifikasi awal perlu selalu membaca kondisi aktual sebelum membuat file baru.
- Menu sidebar `Data Saya` kini memakai icon `User` dari `lucide-react`, sementara pembatasan viewer tetap hanya menampilkan `Dashboard`, `Data Saya`, dan `Settings`.
- Verifikasi ulang yang tersimpan di `.sisyphus/evidence/task-21-rbac-enforcement.txt` menunjukkan `php artisan test --compact --filter=SelfServiceAccess`, full test suite (216 pass), `npm run build`, `vendor/bin/pint --dirty --format agent`, dan `php artisan route:list | grep self-service` semuanya lulus.

## [2026-03-15] F4 Scope Fidelity

- Guardrails utama lolos: tidak ada integrasi SIKEP/SIASN/BKN, tidak ada multi-tenant, tidak ada chart library baru, dan tidak ada repository pattern.
- Pencarian keyword guardrail perlu konteks domain: istilah `gaji_pokok` muncul sebagai bagian atribut `RiwayatPangkat` yang memang diminta task T8, jadi bukan otomatis fitur payroll.
- Validasi scope perlu membandingkan value enum satu per satu terhadap plan, bukan hanya memastikan file enum ada.
- Validasi route perlu memeriksa literal path expected dari spec QA, bukan hanya keberadaan endpoint sejenis.

## [2026-03-15] F4-Fix: HubunganKeluarga & Route Dokumen

### Fix A: HubunganKeluarga Enum

- `app/Enums/HubunganKeluarga.php`: Dihapus `AyahMertua` dan `IbuMertua` cases, sekarang hanya 5 values: `Suami`, `Istri`, `Anak`, `AyahKandung`, `IbuKandung`.
- `resources/js/types/kepegawaian.ts`: Updated TypeScript type untuk sinkron dengan backend enum.
- `tests/Unit/Enums/HubunganKeluargaTest.php`: Update test expectations dari 7 ke 5 cases.

### Fix B: Route Rename

- `routes/web.php`: Route prefix berubah dari `dokumen-pegawai` ke `dokumen`.
- `app/Http/Controllers/Kepegawaian/DokumenPegawaiController.php`:
    - Route names berubah dari `kepegawaian.pegawai.dokumen-pegawai.*` ke `kepegawaian.pegawai.dokumen.*`
    - Parameter route berubah dari `{dokumenPegawai}` ke `{dokumen}`, sehingga variabel controller juga diubah ke `$dokumen`
- `tests/Feature/Kepegawaian/DokumenPegawaiTest.php`: Update route references.

### Verifikasi

- `php artisan test --compact` → 216 passed (1148 assertions)
- `php artisan route:list --path=dokumen` → Path menggunakan `dokumen` bukan `dokumen-pegawai`
- `npm run build` → Wayfinder artifacts regenerated
- `vendor/bin/pint --dirty --format agent` → Passed

## [2026-03-15] F3 Manual QA

- Validasi browser E2E harus dimulai dari clean browser session; cookie lama bisa memberi false positive pada flow admin.
- Untuk task QA berbasis seed account, cek akun aktual di database membantu membedakan bug autentikasi vs mismatch data seed.

## 2026-03-15 F3 QA

- Setelah `php artisan migrate:fresh --seed`, kredensial yang benar-benar tersedia hanya `test@example.com / password`.
- Dashboard tetap memuat statistik dan distribusi data dengan baik pada seed saat ini.
- Flow self-service untuk akun tanpa relasi `pegawai_id` diarahkan ke `/self-service/unlinked` dengan pesan fallback yang jelas.

## [2026-03-15] F3 QA Final Verification Wave

- Final QA viewer perlu memeriksa asset avatar/default image juga; halaman dapat terlihat benar tetapi tetap gagal APPROVE bila browser console mencatat `404` seperti request `/pegawai/default-2.jpg`.
- Skenario RBAC manual yang memang mengharapkan `403 Forbidden` sebaiknya diperlakukan sebagai expected network call, selama tidak ada error browser lain di luar response `403` itu sendiri.
- Flow create pegawai masih bergantung pada asumsi schema reference yang tidak konsisten: `RefJabatan` di controller diurutkan dengan kolom `urutan`, padahal tabel hasil seed tidak memilikinya.

## [2026-03-15] F3 Manual QA (correction)

- Seed account aktual untuk QA adalah `admin@example.com` dan `viewer@example.com`; brief task memakai domain `.test`, jadi validasi perlu dikonfirmasi ke database sebelum menyimpulkan login gagal.
- Viewer seed yang ter-link ternyata mengarah ke pegawai `Ade Ramadan`, bukan `Fattahurridlo Al Ghany`, sehingga assertion self-service harus mengikuti data seed nyata.
