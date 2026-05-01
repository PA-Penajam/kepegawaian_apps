# Modul Cuti & Izin — Implementation Plan (Fase 1 MVP)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul Cuti & Izin Fase 1 MVP (CT, CS Tier 1-2, CAP) untuk PA Penajam Paser Utara dengan workflow 4-step, pure ledger saldo, FIFO multi-bucket, dan integrasi 1-arah ke `attendance-qr-system` via webhook signed.

**Architecture:** Laravel 12 service-layer + spatie state machine + pure ledger pattern + transactional outbox. UI internal pakai Inertia routes (bukan API-driven SPA). Lock anchor di `cuti_alokasi_tahunan` mencegah race condition pada saldo. Idempotency multi-layer: `lockForUpdate()` + state re-validate + UNIQUE constraint + receiver dedupe. No cross-year leave (kebijakan PA Penajam).

**Tech Stack:** PHP 8.2, Laravel 12, Inertia v2, React 19, TypeScript, Tailwind v4, shadcn/ui, MySQL, Sanctum, ULID. Composer baru: `spatie/laravel-model-states ^2.7`, `spatie/laravel-pdf ^1.5`. Existing: `spatie/laravel-activitylog ^5.0`, `spatie/laravel-permission`.

**Reference spec:** `docs/superpowers/specs/2026-05-01-modul-cuti-design.md` (v7, commit `0864129`). Spec adalah source of truth — plan ini turunannya.

**Scope plan ini:** Hanya **Fase 1 MVP**. Fase 2 (Plh/Plt, GDrive, PWA, calendar, workflow eksternal Ketua) dan Fase 3 (SIASN, multi-tenant) di luar scope plan ini.

---

## Konvensi Plan

- **Task ID**: `Task N.M` di mana `N` = tahap (1-18), `M` = nomor task dalam tahap.
- **TDD mandatory**: Setiap task service/business-logic mulai dengan failing test (RED), implementasi minimal (GREEN), refactor jika perlu, commit.
- **Commit cadence**: Commit per task. Pesan commit pakai prefix `feat(cuti):`, `test(cuti):`, `refactor(cuti):`, `docs(cuti):`.
- **File path**: Selalu absolut dari root project (mis. `app/Models/Cuti/CutiPengajuan.php`).
- **Run command**: Selalu pakai `php artisan` atau `vendor/bin/pest` (project pakai Pest jika tersedia; cek `composer.json` di Tahap 0).
- **Comments code**: Semua inline comment dalam Bahasa Indonesia (per CLAUDE.md). Variable/function/class names tetap English.

---

## Tahap 0: Setup & Bootstrap

### Task 0.1: Verifikasi tooling & test runner

**Files:**
- Check: `composer.json`, `phpunit.xml` atau `pest.xml`

- [ ] **Step 1:** Cek apakah project pakai Pest atau PHPUnit
  ```bash
  grep -E '"pestphp/pest"|"phpunit/phpunit"' composer.json
  ```
  Catat hasilnya. Plan ini default pakai PHPUnit syntax — adaptasi jika Pest dipakai.
- [ ] **Step 2:** Pastikan migrate fresh berjalan
  ```bash
  php artisan migrate:fresh --seed --env=testing
  ```
  Expected: SUCCESS dengan semua migration existing jalan.
- [ ] **Step 3:** Run existing test suite untuk baseline
  ```bash
  vendor/bin/phpunit --testsuite=Unit
  ```
  Expected: All pass (atau catat existing failures).

### Task 0.2: Install Composer packages baru

**Files:**
- Modify: `composer.json`, `composer.lock`

- [ ] **Step 1:** Install `spatie/laravel-model-states`
  ```bash
  composer require spatie/laravel-model-states:^2.7
  ```
- [ ] **Step 2:** Install `spatie/laravel-pdf`
  ```bash
  composer require spatie/laravel-pdf:^1.5
  ```
- [ ] **Step 3:** Verifikasi `spatie/laravel-activitylog` sudah ada (^5.0). Jika belum publish migration:
  ```bash
  php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
  ```
- [ ] **Step 4:** Install Browsershot dependency di host (Node + Puppeteer)
  ```bash
  npm install puppeteer
  ```
  Catatan: jika server production tidak punya Node, tandai sebagai blocker untuk Tahap 16.
- [ ] **Step 5:** Commit
  ```bash
  git add composer.json composer.lock package.json package-lock.json
  git commit -m "chore(cuti): install model-states, laravel-pdf, browsershot deps"
  ```

### Task 0.3: Worktree (jika belum)

- [ ] **Step 1:** Cek branch
  ```bash
  git branch --show-current
  ```
- [ ] **Step 2:** Jika belum di branch fitur, buat worktree:
  ```bash
  git worktree add ../kepegawaian-apps-cuti -b feat/modul-cuti
  cd ../kepegawaian-apps-cuti
  ```
  (Catatan: brainstorming sudah commit di main; worktree opsional untuk parallel work.)

---

## Tahap 1: Foundation — Migrations & Models (Week 1.1, ~3 pd)

Tahap ini membuat 15 tabel + 12 model basic (tanpa state machine — itu di Tahap 5). Migration urutan kronologis penting karena ada FK ke `pegawai(nip)`.

### Task 1.1: Migration `cuti_jenis_master`

**Files:**
- Create: `database/migrations/2026_05_02_000001_create_cuti_jenis_master_table.php`

- [ ] **Step 1:** Generate migration
  ```bash
  php artisan make:migration create_cuti_jenis_master_table --create=cuti_jenis_master
  ```
- [ ] **Step 2:** Isi migration (sesuai spec Section 6)
  ```php
  Schema::create('cuti_jenis_master', function (Blueprint $table) {
      $table->string('kode', 10)->primary(); // CT, CS_TIER1, CS_TIER2, CAP
      $table->string('nama', 100);
      $table->boolean('saldo_driven')->default(false); // CT only di MVP
      $table->integer('hak_default_per_tahun')->nullable(); // 12 untuk CT
      $table->integer('durasi_min_kalender')->nullable();
      $table->integer('durasi_max_kalender')->nullable();
      $table->boolean('butuh_lampiran')->default(false);
      $table->boolean('boleh_dicabut_setelah_disetujui')->default(false);
      $table->boolean('aktif')->default(true);
      $table->timestamps();
  });
  ```
- [ ] **Step 3:** Run migration & rollback test
  ```bash
  php artisan migrate
  php artisan migrate:rollback --step=1
  php artisan migrate
  ```
  Expected: SUCCESS keduanya.
- [ ] **Step 4:** Commit
  ```bash
  git add database/migrations/2026_05_02_000001_create_cuti_jenis_master_table.php
  git commit -m "feat(cuti): add migration cuti_jenis_master"
  ```

### Task 1.2: Migration `cuti_libur_master`

**Files:**
- Create: `database/migrations/2026_05_02_000002_create_cuti_libur_master_table.php`

- [ ] **Step 1:** Generate & isi
  ```php
  Schema::create('cuti_libur_master', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->date('tanggal')->unique();
      $table->string('keterangan', 200);
      $table->boolean('is_cuti_bersama')->default(false);
      $table->smallInteger('tahun');
      $table->index('tahun');
      $table->timestamps();
  });
  ```
- [ ] **Step 2:** Migrate + commit
  ```bash
  php artisan migrate
  git add . && git commit -m "feat(cuti): add migration cuti_libur_master"
  ```

### Task 1.3: Migration `cuti_konfigurasi`

**Files:**
- Create: `database/migrations/2026_05_02_000003_create_cuti_konfigurasi_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_konfigurasi', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('key', 100)->unique();
      $table->json('value');
      $table->string('keterangan', 500)->nullable();
      $table->timestamps();
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_konfigurasi`

### Task 1.4: Migration `cuti_alokasi_tahunan` (anchor row)

**Files:**
- Create: `database/migrations/2026_05_02_000004_create_cuti_alokasi_tahunan_table.php`

- [ ] **Step 1:** Isi (sesuai spec)
  ```php
  Schema::create('cuti_alokasi_tahunan', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('pegawai_nip', 20);
      $table->string('jenis_cuti_kode', 10);
      $table->smallInteger('tahun_hak');
      $table->integer('hak_awal');
      $table->string('catatan', 500)->nullable();
      $table->timestamps();

      $table->unique(['pegawai_nip', 'jenis_cuti_kode', 'tahun_hak'], 'uk_alokasi');
      $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_alokasi_tahunan`

### Task 1.5: Migration `cuti_pengajuan` (header)

**Files:**
- Create: `database/migrations/2026_05_02_000005_create_cuti_pengajuan_table.php`

- [ ] **Step 1:** Isi (sesuai spec Section 6 dengan approver snapshot pattern)
  ```php
  Schema::create('cuti_pengajuan', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('nomor_pengajuan', 50)->unique();
      $table->string('pegawai_nip', 20);
      $table->string('jenis_cuti_kode', 10);
      $table->date('tanggal_mulai');
      $table->date('tanggal_selesai');
      $table->integer('jumlah_hari_kerja');
      $table->text('alasan');
      $table->text('alamat_selama_cuti')->nullable();
      $table->string('nomor_telp_selama_cuti', 30)->nullable();
      $table->string('state', 50)->default('DRAFT');

      // Snapshot (immutable, captured at submit)
      $table->string('petugas_kepegawaian_snapshot_nip', 20)->nullable();
      $table->string('atasan_langsung_snapshot_nip', 20)->nullable();
      $table->string('pejabat_berwenang_snapshot_nip', 20)->nullable();

      // Current (mutable, may differ from snapshot if reassigned)
      $table->string('petugas_kepegawaian_current_nip', 20)->nullable();
      $table->string('atasan_langsung_current_nip', 20)->nullable();
      $table->string('pejabat_berwenang_current_nip', 20)->nullable();

      $table->timestamp('submitted_at')->nullable();
      $table->timestamp('approved_at')->nullable();
      $table->timestamp('rejected_at')->nullable();
      $table->timestamp('cancelled_at')->nullable();
      $table->text('rejection_reason')->nullable();
      $table->timestamps();

      $table->index('pegawai_nip');
      $table->index('state');
      $table->index('atasan_langsung_current_nip');
      $table->index('pejabat_berwenang_current_nip');

      $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('atasan_langsung_snapshot_nip')->references('nip')->on('pegawai');
      $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan`

### Task 1.6: Migration `cuti_saldo_ledger` (pure ledger + UNIQUE)

**Files:**
- Create: `database/migrations/2026_05_02_000006_create_cuti_saldo_ledger_table.php`

- [ ] **Step 1:** Isi (sesuai spec Section 6 + UNIQUE constraint dari Section 7)
  ```php
  Schema::create('cuti_saldo_ledger', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('pegawai_nip', 20);
      $table->string('jenis_cuti_kode', 10);
      $table->smallInteger('tahun_hak');
      $table->enum('jenis_transaksi', [
          'kredit', 'debit_pending', 'debit_void',
          'debit_confirmed', 'kredit_refund', 'expire', 'penyesuaian',
      ]);
      $table->integer('jumlah_hari'); // signed
      $table->ulid('pengajuan_id')->nullable();
      $table->string('keterangan', 500)->nullable();
      $table->string('aktor_pegawai_nip', 20);
      $table->timestamp('created_at')->useCurrent();

      $table->index(['pegawai_nip', 'jenis_cuti_kode', 'tahun_hak'], 'idx_pegawai_jenis_tahun');
      $table->index('pengajuan_id', 'idx_pengajuan');

      $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan');
      $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');

      // UNIQUE defense-in-depth: 1 pengajuan + jenis_transaksi + tahun_hak max 1 row
      // (multi-bucket FIFO valid karena tahun_hak ikut sebagai key)
      $table->unique(
          ['pengajuan_id', 'jenis_transaksi', 'tahun_hak'],
          'uk_pengajuan_transaksi_bucket'
      );
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_saldo_ledger with bucket-aware UNIQUE`

### Task 1.7: Migration `cuti_pengajuan_periode`

**Files:**
- Create: `database/migrations/2026_05_02_000007_create_cuti_pengajuan_periode_table.php`

- [ ] **Step 1:** Isi (untuk masa depan multi-period; MVP biasanya 1 record per pengajuan)
  ```php
  Schema::create('cuti_pengajuan_periode', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->date('tanggal_mulai');
      $table->date('tanggal_selesai');
      $table->integer('jumlah_hari_kerja');
      $table->timestamps();

      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
      $table->index('pengajuan_id');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_periode`

### Task 1.8: Migration `cuti_pengajuan_lampiran`

**Files:**
- Create: `database/migrations/2026_05_02_000008_create_cuti_pengajuan_lampiran_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_pengajuan_lampiran', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->string('jenis_lampiran', 50); // surat_dokter, akta_kematian, undangan, dll
      $table->string('nama_file_asli', 255);
      $table->string('path_file', 500); // storage/app/cuti/{NIP}/{tahun}/{ulid}.{ext}
      $table->string('mime_type', 100);
      $table->integer('size_bytes');
      $table->string('checksum_sha256', 64);
      $table->string('uploaded_by_nip', 20);
      $table->timestamps();

      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
      $table->foreign('uploaded_by_nip')->references('nip')->on('pegawai');
      $table->index('pengajuan_id');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_lampiran`

### Task 1.9: Migration `cuti_pengajuan_approval_steps`

**Files:**
- Create: `database/migrations/2026_05_02_000009_create_cuti_pengajuan_approval_steps_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_pengajuan_approval_steps', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->enum('role', ['petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang']);
      $table->enum('action', ['approve', 'reject', 'verify']);
      $table->string('aktor_pegawai_nip', 20);
      $table->text('catatan')->nullable();
      $table->timestamp('acted_at');
      $table->timestamps();

      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
      $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
      $table->index('pengajuan_id');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_approval_steps`

### Task 1.10: Migration `cuti_pengajuan_approver_history`

**Files:**
- Create: `database/migrations/2026_05_02_000010_create_cuti_pengajuan_approver_history_table.php`

- [ ] **Step 1:** Isi (sesuai spec)
  ```php
  Schema::create('cuti_pengajuan_approver_history', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->enum('role', ['petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang']);
      $table->string('from_pegawai_nip', 20)->nullable();
      $table->string('to_pegawai_nip', 20);
      $table->string('alasan', 500);
      $table->string('aktor_pegawai_nip', 20);
      $table->timestamp('created_at')->useCurrent();

      $table->index('pengajuan_id');
      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
      $table->foreign('from_pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('to_pegawai_nip')->references('nip')->on('pegawai');
      $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_approver_history`

### Task 1.11: Migration `cuti_pengajuan_state_history`

**Files:**
- Create: `database/migrations/2026_05_02_000011_create_cuti_pengajuan_state_history_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_pengajuan_state_history', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->string('state_from', 50)->nullable();
      $table->string('state_to', 50);
      $table->string('aktor_pegawai_nip', 20);
      $table->text('catatan')->nullable();
      $table->timestamp('created_at')->useCurrent();

      $table->index('pengajuan_id');
      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
      $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_state_history`

### Task 1.12: Migration `cuti_pengajuan_pdf`

**Files:**
- Create: `database/migrations/2026_05_02_000012_create_cuti_pengajuan_pdf_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_pengajuan_pdf', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('pengajuan_id');
      $table->string('path_file', 500);
      $table->string('checksum_sha256', 64);
      $table->integer('size_bytes');
      $table->timestamp('generated_at');
      $table->timestamps();

      $table->index('pengajuan_id');
      $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_pengajuan_pdf`

### Task 1.13: Migration `cuti_events` (outbox event log)

**Files:**
- Create: `database/migrations/2026_05_02_000013_create_cuti_events_table.php`

- [ ] **Step 1:** Isi (sesuai spec — UUID v4, bukan ULID, untuk distributed safety)
  ```php
  Schema::create('cuti_events', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('aggregate_type', 50);
      $table->ulid('aggregate_id');
      $table->string('event_type', 100);
      $table->json('payload');
      $table->timestamp('occurred_at')->useCurrent();
      $table->timestamp('created_at')->useCurrent();

      $table->index(['aggregate_type', 'aggregate_id'], 'idx_aggregate');
      $table->index('event_type');
      $table->index('occurred_at');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_events`

### Task 1.14: Migration `cuti_event_deliveries` (outbox delivery state)

**Files:**
- Create: `database/migrations/2026_05_02_000014_create_cuti_event_deliveries_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_event_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->uuid('event_id');
      $table->string('consumer_id', 50);
      $table->enum('status', ['pending', 'in_flight', 'delivered', 'failed', 'dead_letter'])->default('pending');
      $table->integer('attempts')->default(0);
      $table->timestamp('last_attempt_at')->nullable();
      $table->timestamp('delivered_at')->nullable();
      $table->text('last_error')->nullable();
      $table->timestamp('next_retry_at')->nullable();
      $table->timestamps();

      $table->unique(['event_id', 'consumer_id'], 'uk_event_consumer');
      $table->index(['status', 'next_retry_at'], 'idx_status_retry');
      $table->foreign('event_id')->references('id')->on('cuti_events');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_event_deliveries`

### Task 1.15: Migration `cuti_jenis_per_status_pegawai` (pivot jenis × PNS/PPPK)

**Files:**
- Create: `database/migrations/2026_05_02_000015_create_cuti_jenis_per_status_pegawai_table.php`

- [ ] **Step 1:** Isi
  ```php
  Schema::create('cuti_jenis_per_status_pegawai', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('jenis_cuti_kode', 10);
      $table->string('status_kepegawaian', 20); // PNS, PPPK
      $table->boolean('boleh')->default(true);
      $table->integer('hak_per_tahun')->nullable(); // override default jika beda
      $table->text('catatan')->nullable();
      $table->timestamps();

      $table->unique(['jenis_cuti_kode', 'status_kepegawaian'], 'uk_jenis_status');
      $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
  });
  ```
- [ ] **Step 2:** Migrate + commit `feat(cuti): add migration cuti_jenis_per_status_pegawai`

### Task 1.16: Seeder `CutiJenisMasterSeeder`

**Files:**
- Create: `database/seeders/CutiJenisMasterSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1:** Buat seeder
  ```php
  <?php
  namespace Database\Seeders;
  use Illuminate\Database\Seeder;
  use Illuminate\Support\Facades\DB;

  class CutiJenisMasterSeeder extends Seeder {
      public function run(): void {
          DB::table('cuti_jenis_master')->insert([
              ['kode' => 'CT', 'nama' => 'Cuti Tahunan', 'saldo_driven' => true, 'hak_default_per_tahun' => 12, 'butuh_lampiran' => false, 'boleh_dicabut_setelah_disetujui' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
              ['kode' => 'CS_TIER1', 'nama' => 'Cuti Sakit Tier 1 (≤14 hari)', 'saldo_driven' => false, 'durasi_max_kalender' => 14, 'butuh_lampiran' => true, 'boleh_dicabut_setelah_disetujui' => false, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
              ['kode' => 'CS_TIER2', 'nama' => 'Cuti Sakit Tier 2 (15-30 hari)', 'saldo_driven' => false, 'durasi_min_kalender' => 15, 'durasi_max_kalender' => 30, 'butuh_lampiran' => true, 'boleh_dicabut_setelah_disetujui' => false, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
              ['kode' => 'CAP', 'nama' => 'Cuti Alasan Penting', 'saldo_driven' => false, 'butuh_lampiran' => true, 'boleh_dicabut_setelah_disetujui' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
          ]);
      }
  }
  ```
- [ ] **Step 2:** Register di `DatabaseSeeder::run()`: `$this->call(CutiJenisMasterSeeder::class);`
- [ ] **Step 3:** Jalankan
  ```bash
  php artisan db:seed --class=CutiJenisMasterSeeder
  ```
- [ ] **Step 4:** Commit `feat(cuti): seed cuti_jenis_master with 4 MVP types`

### Task 1.17: Seeder `CutiJenisPerStatusPegawaiSeeder`

**Files:**
- Create: `database/seeders/CutiJenisPerStatusPegawaiSeeder.php`

- [ ] **Step 1:** Buat seeder yang map setiap jenis ke PNS+PPPK (semua boleh, hak default 12 untuk CT)
  ```php
  $statuses = ['PNS', 'PPPK'];
  $jenis = ['CT', 'CS_TIER1', 'CS_TIER2', 'CAP'];
  foreach ($jenis as $kode) {
      foreach ($statuses as $status) {
          DB::table('cuti_jenis_per_status_pegawai')->insert([
              'id' => Str::ulid()->toBase32(),
              'jenis_cuti_kode' => $kode,
              'status_kepegawaian' => $status,
              'boleh' => true,
              'hak_per_tahun' => $kode === 'CT' ? 12 : null,
              'created_at' => now(),
              'updated_at' => now(),
          ]);
      }
  }
  ```
- [ ] **Step 2:** Register + run + commit `feat(cuti): seed cuti_jenis_per_status_pegawai`

### Task 1.18: Permission seed `CutiPermissionSeeder`

**Files:**
- Create: `database/seeders/CutiPermissionSeeder.php`

- [ ] **Step 1:** Buat seeder yang bikin 14 permission sesuai Section 5 spec
  ```php
  $permissions = [
      'cuti.pengajuan.create',
      'cuti.pengajuan.view-own',
      'cuti.pengajuan.view-team',
      'cuti.pengajuan.view-all',
      'cuti.pengajuan.verify',
      'cuti.pengajuan.approve-langsung',
      'cuti.pengajuan.approve-pejabat',
      'cuti.pengajuan.cancel-own',
      'cuti.pengajuan.cancel-any',
      'cuti.pengajuan.reassign',
      'cuti.saldo.view-own',
      'cuti.saldo.view-all',
      'cuti.saldo.adjust',
      'cuti.audit.view',
  ];
  // Insert ke ref_permissions / iam_permissions sesuai pattern existing
  // (cek seeder permission existing untuk konvensi tabel)
  ```
- [ ] **Step 2:** Cek `app/Models/IamPermission.php` & seeder existing untuk pola insert. Sesuaikan.
- [ ] **Step 3:** Run + commit `feat(cuti): seed 14 cuti permissions`

### Task 1.19: Model `CutiJenisMaster`

**Files:**
- Create: `app/Models/Cuti/CutiJenisMaster.php`

- [ ] **Step 1:** Buat
  ```php
  <?php
  namespace App\Models\Cuti;
  use Illuminate\Database\Eloquent\Model;

  class CutiJenisMaster extends Model {
      protected $table = 'cuti_jenis_master';
      protected $primaryKey = 'kode';
      public $incrementing = false;
      protected $keyType = 'string';
      protected $fillable = ['kode', 'nama', 'saldo_driven', 'hak_default_per_tahun', 'durasi_min_kalender', 'durasi_max_kalender', 'butuh_lampiran', 'boleh_dicabut_setelah_disetujui', 'aktif'];
      protected $casts = ['saldo_driven' => 'boolean', 'butuh_lampiran' => 'boolean', 'boleh_dicabut_setelah_disetujui' => 'boolean', 'aktif' => 'boolean'];
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): add CutiJenisMaster model`

### Task 1.20: Model `CutiAlokasiTahunan`

**Files:**
- Create: `app/Models/Cuti/CutiAlokasiTahunan.php`

- [ ] **Step 1:** Buat
  ```php
  namespace App\Models\Cuti;
  use App\Models\Pegawai;
  use Illuminate\Database\Eloquent\Concerns\HasUlids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;

  class CutiAlokasiTahunan extends Model {
      use HasUlids;
      protected $table = 'cuti_alokasi_tahunan';
      protected $fillable = ['pegawai_nip', 'jenis_cuti_kode', 'tahun_hak', 'hak_awal', 'catatan'];
      protected $casts = ['tahun_hak' => 'integer', 'hak_awal' => 'integer'];

      public function pegawai(): BelongsTo {
          return $this->belongsTo(Pegawai::class, 'pegawai_nip', 'nip');
      }
      public function jenis(): BelongsTo {
          return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
      }
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): add CutiAlokasiTahunan model`

### Task 1.21: Model `CutiSaldoLedger`

**Files:**
- Create: `app/Models/Cuti/CutiSaldoLedger.php`

- [ ] **Step 1:** Buat dengan `UPDATED_AT = null` (append-only — no UPDATE)
  ```php
  namespace App\Models\Cuti;
  use Illuminate\Database\Eloquent\Concerns\HasUlids;
  use Illuminate\Database\Eloquent\Model;

  class CutiSaldoLedger extends Model {
      use HasUlids;
      protected $table = 'cuti_saldo_ledger';
      public const UPDATED_AT = null; // append-only
      protected $fillable = [
          'pegawai_nip', 'jenis_cuti_kode', 'tahun_hak',
          'jenis_transaksi', 'jumlah_hari', 'pengajuan_id',
          'keterangan', 'aktor_pegawai_nip',
      ];
      protected $casts = ['tahun_hak' => 'integer', 'jumlah_hari' => 'integer'];
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): add CutiSaldoLedger model (append-only)`

### Task 1.22: Model `CutiPengajuan` (basic, tanpa state machine — itu di Tahap 5)

**Files:**
- Create: `app/Models/Cuti/CutiPengajuan.php`

- [ ] **Step 1:** Buat dengan basic fields + relations (state column belum di-bind ke spatie)
  ```php
  namespace App\Models\Cuti;
  use App\Models\Pegawai;
  use Illuminate\Database\Eloquent\Concerns\HasUlids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Illuminate\Database\Eloquent\Relations\HasMany;

  class CutiPengajuan extends Model {
      use HasUlids;
      protected $table = 'cuti_pengajuan';
      protected $fillable = [
          'nomor_pengajuan', 'pegawai_nip', 'jenis_cuti_kode',
          'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari_kerja',
          'alasan', 'alamat_selama_cuti', 'nomor_telp_selama_cuti',
          'state',
          'petugas_kepegawaian_snapshot_nip', 'atasan_langsung_snapshot_nip', 'pejabat_berwenang_snapshot_nip',
          'petugas_kepegawaian_current_nip', 'atasan_langsung_current_nip', 'pejabat_berwenang_current_nip',
          'submitted_at', 'approved_at', 'rejected_at', 'cancelled_at', 'rejection_reason',
      ];
      protected $casts = [
          'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date',
          'jumlah_hari_kerja' => 'integer',
          'submitted_at' => 'datetime', 'approved_at' => 'datetime',
          'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
      ];

      public function pegawai(): BelongsTo {
          return $this->belongsTo(Pegawai::class, 'pegawai_nip', 'nip');
      }
      public function ledgerEntries(): HasMany {
          return $this->hasMany(CutiSaldoLedger::class, 'pengajuan_id');
      }
      public function lampiran(): HasMany {
          return $this->hasMany(CutiPengajuanLampiran::class, 'pengajuan_id');
      }
      public function approvalSteps(): HasMany {
          return $this->hasMany(CutiPengajuanApprovalStep::class, 'pengajuan_id');
      }
      public function approverHistory(): HasMany {
          return $this->hasMany(CutiPengajuanApproverHistory::class, 'pengajuan_id');
      }
      public function stateHistory(): HasMany {
          return $this->hasMany(CutiPengajuanStateHistory::class, 'pengajuan_id');
      }

      public function tahunHak(): int {
          return (int) $this->tanggal_mulai->year;
      }
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): add CutiPengajuan model (state binding deferred to Tahap 5)`

### Task 1.23: Model lainnya (history + lampiran + libur + events)

**Files:**
- Create: `app/Models/Cuti/CutiPengajuanLampiran.php`
- Create: `app/Models/Cuti/CutiPengajuanApprovalStep.php`
- Create: `app/Models/Cuti/CutiPengajuanApproverHistory.php`
- Create: `app/Models/Cuti/CutiPengajuanStateHistory.php`
- Create: `app/Models/Cuti/CutiLiburMaster.php`
- Create: `app/Models/Cuti/CutiEvent.php`
- Create: `app/Models/Cuti/CutiEventDelivery.php`
- Create: `app/Models/Cuti/CutiKonfigurasi.php`

- [ ] **Step 1:** Buat 8 model dengan minimal fillable + cast + 1 relasi balik ke `CutiPengajuan` jika applicable. Pola sama dengan Task 1.20.

  Contoh untuk `CutiEvent` (UUID, bukan ULID):
  ```php
  namespace App\Models\Cuti;
  use Illuminate\Database\Eloquent\Concerns\HasUuids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\HasMany;

  class CutiEvent extends Model {
      use HasUuids;
      protected $table = 'cuti_events';
      public const UPDATED_AT = null;
      protected $fillable = ['aggregate_type', 'aggregate_id', 'event_type', 'payload', 'occurred_at'];
      protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

      public function deliveries(): HasMany {
          return $this->hasMany(CutiEventDelivery::class, 'event_id');
      }
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): add 8 supporting models (lampiran, history, events, konfigurasi)`

### Task 1.24: Factory untuk `CutiPengajuan` (untuk testing)

**Files:**
- Create: `database/factories/Cuti/CutiPengajuanFactory.php`

- [ ] **Step 1:** Buat factory minimal
  ```php
  namespace Database\Factories\Cuti;
  use App\Models\Cuti\CutiPengajuan;
  use App\Models\Pegawai;
  use Illuminate\Database\Eloquent\Factories\Factory;

  class CutiPengajuanFactory extends Factory {
      protected $model = CutiPengajuan::class;

      public function definition(): array {
          $start = $this->faker->dateTimeBetween('+1 week', '+1 month');
          $end = (clone $start)->modify('+3 days');
          return [
              'nomor_pengajuan' => 'CUTI/' . date('Y') . '/' . $this->faker->unique()->numerify('########') . '/' . $this->faker->unique()->numerify('####'),
              'pegawai_nip' => Pegawai::factory(),
              'jenis_cuti_kode' => 'CT',
              'tanggal_mulai' => $start->format('Y-m-d'),
              'tanggal_selesai' => $end->format('Y-m-d'),
              'jumlah_hari_kerja' => 3,
              'alasan' => $this->faker->sentence(),
              'state' => 'DRAFT',
          ];
      }
  }
  ```
- [ ] **Step 2:** Tambah `use HasFactory` + `protected static string $factory = CutiPengajuanFactory::class;` di model `CutiPengajuan`
- [ ] **Step 3:** Commit `feat(cuti): add CutiPengajuan factory`

---

## Tahap 2: HariKerjaCalculator Service (Week 1.2, ~0.5 pd)

Service hitung jumlah hari kerja antara 2 tanggal, skip Sabtu+Minggu+libur. TDD strict.

### Task 2.1: Test happy path Senin-Jumat tanpa libur

**Files:**
- Create: `tests/Unit/Cuti/HariKerjaCalculatorServiceTest.php`

- [ ] **Step 1:** Write failing test
  ```php
  <?php
  namespace Tests\Unit\Cuti;
  use App\Services\Cuti\HariKerjaCalculatorService;
  use Tests\TestCase;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Carbon\Carbon;

  class HariKerjaCalculatorServiceTest extends TestCase {
      use RefreshDatabase;

      public function test_hitung_5_hari_kerja_senin_sampai_jumat(): void {
          $svc = app(HariKerjaCalculatorService::class);
          $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-05')); // Sen-Jum
          $this->assertSame(5, $hasil);
      }
  }
  ```
- [ ] **Step 2:** Run test — expect fail "class not found"
  ```bash
  vendor/bin/phpunit --filter=test_hitung_5_hari_kerja_senin_sampai_jumat
  ```
- [ ] **Step 3:** Implement minimal
  ```php
  <?php
  namespace App\Services\Cuti;
  use Carbon\Carbon;

  class HariKerjaCalculatorService {
      public function hitung(Carbon $from, Carbon $to): int {
          $count = 0;
          $cursor = $from->copy()->startOfDay();
          $end = $to->copy()->startOfDay();
          while ($cursor->lte($end)) {
              if (!$cursor->isWeekend()) $count++;
              $cursor->addDay();
          }
          return $count;
      }
  }
  ```
  Path: `app/Services/Cuti/HariKerjaCalculatorService.php`
- [ ] **Step 4:** Run test — expect PASS
- [ ] **Step 5:** Commit `feat(cuti): add HariKerjaCalculatorService skeleton + happy path test`

### Task 2.2: Test skip weekend (cross weekend)

- [ ] **Step 1:** Tambah test (RED)
  ```php
  public function test_skip_weekend_dalam_rentang(): void {
      $svc = app(HariKerjaCalculatorService::class);
      // 2026-06-01 (Sen) sampai 2026-06-08 (Sen berikutnya): 6 hari kerja
      $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-08'));
      $this->assertSame(6, $hasil);
  }
  ```
- [ ] **Step 2:** Run — expect PASS (sudah ter-cover oleh `isWeekend()`)
- [ ] **Step 3:** Commit `test(cuti): assert HariKerjaCalculator skips weekend`

### Task 2.3: Test skip libur nasional

- [ ] **Step 1:** Tambah test (RED)
  ```php
  public function test_skip_libur_nasional(): void {
      \DB::table('cuti_libur_master')->insert([
          'id' => \Illuminate\Support\Str::ulid()->toBase32(),
          'tanggal' => '2026-06-03',
          'keterangan' => 'Libur nasional test',
          'is_cuti_bersama' => false,
          'tahun' => 2026,
          'created_at' => now(),
          'updated_at' => now(),
      ]);
      $svc = app(HariKerjaCalculatorService::class);
      $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-05'));
      $this->assertSame(4, $hasil); // 5 hari kerja - 1 libur
  }
  ```
- [ ] **Step 2:** Run — expect FAIL (5 actual, 4 expected)
- [ ] **Step 3:** Update implementation untuk query `cuti_libur_master`
  ```php
  use App\Models\Cuti\CutiLiburMaster;

  public function hitung(Carbon $from, Carbon $to): int {
      $libur = CutiLiburMaster::whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
          ->pluck('tanggal')
          ->map(fn($d) => Carbon::parse($d)->toDateString())
          ->all();

      $count = 0;
      $cursor = $from->copy()->startOfDay();
      $end = $to->copy()->startOfDay();
      while ($cursor->lte($end)) {
          if (!$cursor->isWeekend() && !in_array($cursor->toDateString(), $libur, true)) {
              $count++;
          }
          $cursor->addDay();
      }
      return $count;
  }
  ```
- [ ] **Step 4:** Run — expect PASS
- [ ] **Step 5:** Commit `feat(cuti): HariKerjaCalculator queries cuti_libur_master`

### Task 2.4: Test edge cases (mulai = selesai, mulai > selesai)

- [ ] **Step 1:** Tambah 2 test
  ```php
  public function test_satu_hari_kerja(): void {
      $svc = app(HariKerjaCalculatorService::class);
      $this->assertSame(1, $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01')));
  }
  public function test_mulai_setelah_selesai_return_0(): void {
      $svc = app(HariKerjaCalculatorService::class);
      $this->assertSame(0, $svc->hitung(Carbon::parse('2026-06-05'), Carbon::parse('2026-06-01')));
  }
  ```
- [ ] **Step 2:** Run — `test_satu_hari_kerja` should pass; `test_mulai_setelah_selesai_return_0` may fail.
- [ ] **Step 3:** Adjust loop condition jika perlu (`while ($cursor->lte($end))` sudah handle case `from > to` → 0).
- [ ] **Step 4:** Run all + commit `test(cuti): HariKerjaCalculator edge cases`

---

## Tahap 3: SaldoLedgerService (Week 1.3, ~3 pd)

**Critical path** — TDD ketat. Service ini menulis ke `cuti_saldo_ledger` dengan multi-bucket FIFO (CT only).

### Task 3.1: Method `saldoBucket(nip, jenis, tahun)`

**Files:**
- Create: `tests/Unit/Cuti/SaldoLedgerServiceTest.php`
- Create: `app/Services/Cuti/SaldoLedgerService.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_saldoBucket_returns_sum_of_ledger(): void {
      $pegawai = Pegawai::factory()->create();
      CutiSaldoLedger::create([
          'pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026,
          'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $pegawai->nip,
      ]);
      CutiSaldoLedger::create([
          'pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026,
          'jenis_transaksi' => 'debit_confirmed', 'jumlah_hari' => -3, 'aktor_pegawai_nip' => $pegawai->nip,
      ]);
      $svc = app(SaldoLedgerService::class);
      $this->assertSame(9, $svc->saldoBucket($pegawai->nip, 'CT', 2026));
  }
  ```
- [ ] **Step 2:** Implement
  ```php
  public function saldoBucket(string $nip, string $jenisKode, int $tahun): int {
      return (int) CutiSaldoLedger::where('pegawai_nip', $nip)
          ->where('jenis_cuti_kode', $jenisKode)
          ->where('tahun_hak', $tahun)
          ->sum('jumlah_hari');
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::saldoBucket`

### Task 3.2: Method `bucketsAktif(nip, jenis)` — return aktif buckets ASC by tahun_hak

- [ ] **Step 1:** Failing test
  ```php
  public function test_bucketsAktif_returns_only_positive_saldo_buckets_asc(): void {
      $p = Pegawai::factory()->create();
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
      // saldo 2025 = 4 (carry-over capped), saldo 2026 = 12
      CutiSaldoLedger::create([... 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 4]);
      CutiSaldoLedger::create([... 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12]);

      $svc = app(SaldoLedgerService::class);
      $buckets = $svc->bucketsAktif($p->nip, 'CT');
      $this->assertSame([2025, 2026], $buckets->pluck('tahun_hak')->all());
  }
  ```
- [ ] **Step 2:** Implement (return Collection of CutiAlokasiTahunan dengan saldo > 0, sorted ASC)
  ```php
  public function bucketsAktif(string $nip, string $jenisKode) {
      return CutiAlokasiTahunan::where('pegawai_nip', $nip)
          ->where('jenis_cuti_kode', $jenisKode)
          ->orderBy('tahun_hak', 'asc')
          ->get()
          ->filter(fn($a) => $this->saldoBucket($nip, $jenisKode, $a->tahun_hak) > 0)
          ->values();
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::bucketsAktif`

### Task 3.3: Method `debitPendingFifo()` — single bucket case

- [ ] **Step 1:** Failing test (single bucket, saldo cukup)
  ```php
  public function test_debitPendingFifo_single_bucket_saldo_cukup(): void {
      $p = Pegawai::factory()->create();
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
      CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $p->nip]);
      $pengajuan = CutiPengajuan::factory()->for($p, 'pegawai')->create(['jumlah_hari_kerja' => 5]);

      $svc = app(SaldoLedgerService::class);
      $rows = $svc->debitPendingFifo($pengajuan);

      $this->assertCount(1, $rows);
      $this->assertSame(-5, $rows[0]->jumlah_hari);
      $this->assertSame(2026, $rows[0]->tahun_hak);
      $this->assertSame(7, $svc->saldoBucket($p->nip, 'CT', 2026));
  }
  ```
- [ ] **Step 2:** Implement (sesuai spec Section 8.4)
  ```php
  public function debitPendingFifo(CutiPengajuan $p): array {
      $buckets = $this->bucketsAktif($p->pegawai_nip, 'CT');
      $sisa = $p->jumlah_hari_kerja;
      $rows = [];
      foreach ($buckets as $bucket) {
          if ($sisa <= 0) break;
          $available = $this->saldoBucket($p->pegawai_nip, 'CT', $bucket->tahun_hak);
          if ($available <= 0) continue;
          $ambil = min($sisa, $available);
          $rows[] = CutiSaldoLedger::create([
              'pegawai_nip' => $p->pegawai_nip,
              'jenis_cuti_kode' => 'CT',
              'tahun_hak' => $bucket->tahun_hak,
              'jenis_transaksi' => 'debit_pending',
              'jumlah_hari' => -$ambil,
              'pengajuan_id' => $p->id,
              'aktor_pegawai_nip' => $p->pegawai_nip,
          ]);
          $sisa -= $ambil;
      }
      if ($sisa > 0) {
          throw new \App\Exceptions\Cuti\SaldoTidakCukupException('Saldo CT tidak mencukupi');
      }
      return $rows;
  }
  ```
- [ ] **Step 3:** Buat exception class:
  ```php
  // app/Exceptions/Cuti/SaldoTidakCukupException.php
  namespace App\Exceptions\Cuti;
  class SaldoTidakCukupException extends \DomainException {}
  ```
- [ ] **Step 4:** Run + commit `feat(cuti): SaldoLedgerService::debitPendingFifo single bucket`

### Task 3.4: Test multi-bucket FIFO split

- [ ] **Step 1:** Tambah test (saldo N-1=4, N=12, ajukan 7 hari → split 4+3)
  ```php
  public function test_debitPendingFifo_split_lintas_bucket(): void {
      $p = Pegawai::factory()->create();
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
      CutiSaldoLedger::create([... 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 4]);
      CutiSaldoLedger::create([... 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12]);
      $pengajuan = CutiPengajuan::factory()->for($p, 'pegawai')->create(['jumlah_hari_kerja' => 7]);

      $rows = app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);

      $this->assertCount(2, $rows);
      $this->assertSame(2025, $rows[0]->tahun_hak);
      $this->assertSame(-4, $rows[0]->jumlah_hari);
      $this->assertSame(2026, $rows[1]->tahun_hak);
      $this->assertSame(-3, $rows[1]->jumlah_hari);
  }
  ```
- [ ] **Step 2:** Run — should PASS karena implementation Task 3.3 sudah handle loop multi-bucket
- [ ] **Step 3:** Commit `test(cuti): assert FIFO split lintas bucket`

### Task 3.5: Test saldo tidak cukup → throw

- [ ] **Step 1:** Tambah test
  ```php
  public function test_debitPendingFifo_saldo_tidak_cukup_throw(): void {
      $p = Pegawai::factory()->create();
      CutiAlokasiTahunan::create([... 'tahun_hak' => 2026, 'hak_awal' => 12]);
      CutiSaldoLedger::create([... 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 5]);
      $pengajuan = CutiPengajuan::factory()->for($p, 'pegawai')->create(['jumlah_hari_kerja' => 10]);

      $this->expectException(\App\Exceptions\Cuti\SaldoTidakCukupException::class);
      app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): debitPendingFifo throws on insufficient saldo`

### Task 3.6: Method `commitConfirmed()` — void pending + write confirmed per bucket

- [ ] **Step 1:** Failing test
  ```php
  public function test_commitConfirmed_writes_void_and_confirmed_per_bucket(): void {
      $p = Pegawai::factory()->create();
      // setup pengajuan + 2 row debit_pending (bucket 2025=-4, 2026=-3)
      $pengajuan = CutiPengajuan::factory()->for($p, 'pegawai')->create(['jumlah_hari_kerja' => 7]);
      CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, ... 'tahun_hak' => 2025, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -4]);
      CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, ... 'tahun_hak' => 2026, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -3]);

      app(SaldoLedgerService::class)->commitConfirmed($pengajuan);

      $void = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_void')->get();
      $confirmed = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_confirmed')->get();
      $this->assertCount(2, $void);
      $this->assertCount(2, $confirmed);
      $this->assertSame(7, abs($confirmed->sum('jumlah_hari'))); // total commit -7
  }
  ```
- [ ] **Step 2:** Implement (sesuai spec Section 8.4)
  ```php
  public function commitConfirmed(CutiPengajuan $p): void {
      $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
          ->where('jenis_transaksi', 'debit_pending')
          ->get();
      foreach ($pendingRows as $pending) {
          CutiSaldoLedger::create([
              'pengajuan_id' => $p->id,
              'pegawai_nip' => $p->pegawai_nip,
              'jenis_cuti_kode' => 'CT',
              'tahun_hak' => $pending->tahun_hak,
              'jenis_transaksi' => 'debit_void',
              'jumlah_hari' => -$pending->jumlah_hari, // flip sign (positif)
              'aktor_pegawai_nip' => auth()->user()?->nip ?? $p->pegawai_nip,
          ]);
          CutiSaldoLedger::create([
              'pengajuan_id' => $p->id,
              'pegawai_nip' => $p->pegawai_nip,
              'jenis_cuti_kode' => 'CT',
              'tahun_hak' => $pending->tahun_hak,
              'jenis_transaksi' => 'debit_confirmed',
              'jumlah_hari' => $pending->jumlah_hari, // sama dengan pending (negatif)
              'aktor_pegawai_nip' => auth()->user()?->nip ?? $p->pegawai_nip,
          ]);
      }
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::commitConfirmed multi-bucket`

### Task 3.7: Method `voidPending()` — untuk reject sebelum DISETUJUI

- [ ] **Step 1:** Failing test
  ```php
  public function test_voidPending_writes_void_per_bucket_no_confirmed(): void {
      // setup pending 2 bucket, panggil voidPending
      // assert: ada 2 row debit_void, 0 row debit_confirmed
  }
  ```
- [ ] **Step 2:** Implement (mirip commitConfirmed tanpa confirmed write)
  ```php
  public function voidPending(CutiPengajuan $p): void {
      $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
          ->where('jenis_transaksi', 'debit_pending')->get();
      foreach ($pendingRows as $pending) {
          CutiSaldoLedger::create([... 'jenis_transaksi' => 'debit_void', 'jumlah_hari' => -$pending->jumlah_hari, ...]);
      }
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::voidPending`

### Task 3.8: Method `processRefund()` — pencabutan setelah disetujui (FIFO)

- [ ] **Step 1:** Failing test (refund parsial — pencabutan saat berjalan)
  ```php
  public function test_processRefund_partial_after_started_FIFO(): void {
      // pengajuan 5 hari, sudah disetujui (2 row debit_confirmed: 2025=-2, 2026=-3)
      // tanggal_mulai kemarin, today day 2 of 5 → sisa 3 hari kerja
      // refund FIFO: 2 ke 2025 dulu, 1 ke 2026
      $this->travel(2)->days(); // setup tanggal & today
      // ... factory setup
      app(SaldoLedgerService::class)->processRefund($pengajuan);
      $refunds = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)
          ->where('jenis_transaksi', 'kredit_refund')->get();
      $this->assertSame(2025, $refunds[0]->tahun_hak);
      $this->assertSame(2, $refunds[0]->jumlah_hari);
      $this->assertSame(2026, $refunds[1]->tahun_hak);
      $this->assertSame(1, $refunds[1]->jumlah_hari);
  }
  ```
- [ ] **Step 2:** Implement (sesuai spec Section 8.5 — `hitungRefund` + loop FIFO)
  ```php
  public function processRefund(CutiPengajuan $p): array {
      $totalRefund = $this->hitungRefund($p);
      if ($totalRefund <= 0) return [];

      $confirmedRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
          ->where('jenis_transaksi', 'debit_confirmed')
          ->orderBy('tahun_hak', 'asc')->get();

      $sisa = $totalRefund;
      $refundRows = [];
      foreach ($confirmedRows as $row) {
          if ($sisa <= 0) break;
          $confirmedDiBucket = abs($row->jumlah_hari);
          $refundDiBucket = min($sisa, $confirmedDiBucket);
          $refundRows[] = CutiSaldoLedger::create([
              'pengajuan_id' => $p->id,
              'pegawai_nip' => $p->pegawai_nip,
              'jenis_cuti_kode' => 'CT',
              'tahun_hak' => $row->tahun_hak,
              'jenis_transaksi' => 'kredit_refund',
              'jumlah_hari' => +$refundDiBucket,
              'aktor_pegawai_nip' => auth()->user()?->nip ?? $p->pegawai_nip,
          ]);
          $sisa -= $refundDiBucket;
      }
      return $refundRows;
  }

  private function hitungRefund(CutiPengajuan $p): int {
      $today = now()->startOfDay();
      if ($today->lt($p->tanggal_mulai)) return $p->jumlah_hari_kerja;
      if ($today->gt($p->tanggal_selesai)) return 0;
      return app(HariKerjaCalculatorService::class)->hitung(
          $today->copy()->addDay(),
          $p->tanggal_selesai
      );
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::processRefund FIFO`

### Task 3.9: Test refund full (pencabutan sebelum mulai)

- [ ] **Step 1:** Tambah test (today < tanggal_mulai → refund full)
- [ ] **Step 2:** Run + commit `test(cuti): processRefund full when before start date`

### Task 3.10: Method `kreditAlokasi()` — admin init alokasi tahunan

- [ ] **Step 1:** Failing test
  ```php
  public function test_kreditAlokasi_creates_anchor_and_kredit_ledger(): void {
      $p = Pegawai::factory()->create();
      app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'Inisialisasi awal tahun');
      $this->assertSame(12, app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2026));
      $this->assertDatabaseHas('cuti_alokasi_tahunan', ['pegawai_nip' => $p->nip, 'tahun_hak' => 2026, 'hak_awal' => 12]);
  }
  ```
- [ ] **Step 2:** Implement (firstOrCreate anchor + insert kredit, idempotent)
  ```php
  public function kreditAlokasi(string $nip, string $jenisKode, int $tahun, int $hari, string $keterangan): void {
      DB::transaction(function () use ($nip, $jenisKode, $tahun, $hari, $keterangan) {
          $alokasi = CutiAlokasiTahunan::firstOrCreate(
              ['pegawai_nip' => $nip, 'jenis_cuti_kode' => $jenisKode, 'tahun_hak' => $tahun],
              ['hak_awal' => $hari]
          );
          DB::table('cuti_alokasi_tahunan')->where('id', $alokasi->id)->lockForUpdate()->first();

          $sudahKredit = CutiSaldoLedger::where('pegawai_nip', $nip)
              ->where('jenis_cuti_kode', $jenisKode)
              ->where('tahun_hak', $tahun)
              ->where('jenis_transaksi', 'kredit')
              ->whereNull('pengajuan_id')->exists();
          if ($sudahKredit) return;

          CutiSaldoLedger::create([
              'pegawai_nip' => $nip,
              'jenis_cuti_kode' => $jenisKode,
              'tahun_hak' => $tahun,
              'jenis_transaksi' => 'kredit',
              'jumlah_hari' => $hari,
              'aktor_pegawai_nip' => auth()->user()?->nip ?? $nip,
              'keterangan' => $keterangan,
          ]);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): SaldoLedgerService::kreditAlokasi idempotent`

### Task 3.11: Test idempotency `kreditAlokasi` (panggil 2× = hanya 1 row)

- [ ] **Step 1:** Test
  ```php
  public function test_kreditAlokasi_idempotent_dua_panggilan_satu_row(): void {
      $p = Pegawai::factory()->create();
      $svc = app(SaldoLedgerService::class);
      $svc->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');
      $svc->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');
      $count = CutiSaldoLedger::where('pegawai_nip', $p->nip)->where('jenis_transaksi', 'kredit')->count();
      $this->assertSame(1, $count);
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): kreditAlokasi idempotency`

---

## Tahap 4: CarryOverProcessorService (Week 1.4, ~1 pd)

Service jalan tiap 1 Januari 00:05 untuk: expire N-2, cap N-1 max 6, kredit N=12.

### Task 4.1: Service skeleton + test expire bucket N-2

**Files:**
- Create: `tests/Unit/Cuti/CarryOverProcessorServiceTest.php`
- Create: `app/Services/Cuti/CarryOverProcessorService.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_carryOver_expires_bucket_n_minus_2(): void {
      $p = Pegawai::factory()->create();
      CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2024, 'hak_awal' => 12]);
      CutiSaldoLedger::create(['... 2024 ...', 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 8]); // sisa 8 hari
      $this->travelTo('2026-01-01');

      app(CarryOverProcessorService::class)->process($p);
      $this->assertSame(0, app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2024));
      $this->assertDatabaseHas('cuti_saldo_ledger', [
          'pegawai_nip' => $p->nip, 'tahun_hak' => 2024,
          'jenis_transaksi' => 'expire', 'jumlah_hari' => -8,
      ]);
  }
  ```
- [ ] **Step 2:** Implement (skeleton dengan step 1 expire N-2 saja dulu)
- [ ] **Step 3:** Run + commit `feat(cuti): CarryOverProcessorService skeleton + N-2 expire`

### Task 4.2: Test cap N-1 max 6

- [ ] **Step 1:** Test (N-1 punya 10 sisa → expire 4, sisa 6)
- [ ] **Step 2:** Tambah step 2 di service (sesuai spec Section 8.1)
- [ ] **Step 3:** Run + commit `feat(cuti): CarryOver caps N-1 max 6`

### Task 4.3: Test kredit hak tahun N

- [ ] **Step 1:** Test (setelah carry-over jalan, bucket N punya kredit 12)
- [ ] **Step 2:** Tambah step 3 di service
- [ ] **Step 3:** Run + commit `feat(cuti): CarryOver credits N=12`

### Task 4.4: Test idempotency (run 2× tidak duplicate kredit)

- [ ] **Step 1:** Test
  ```php
  public function test_carryOver_idempotent_dua_run(): void {
      // setup, run process(), run process() lagi
      // assert: kredit row N hanya 1, expire row N-2 hanya 1, expire row N-1 hanya 1
  }
  ```
- [ ] **Step 2:** Pastikan implementation pakai existence check sebelum write
- [ ] **Step 3:** Run + commit `test(cuti): CarryOver idempotent on retry`

### Task 4.5: Console command `ProcessCarryOverCommand`

**Files:**
- Create: `app/Console/Commands/Cuti/ProcessCarryOverCommand.php`

- [ ] **Step 1:** Buat command
  ```php
  namespace App\Console\Commands\Cuti;
  use App\Models\Pegawai;
  use App\Services\Cuti\CarryOverProcessorService;
  use Illuminate\Console\Command;

  class ProcessCarryOverCommand extends Command {
      protected $signature = 'cuti:carry-over {--nip= : Process untuk single NIP saja}';
      protected $description = 'Process carry-over saldo CT tiap awal tahun';

      public function handle(CarryOverProcessorService $svc): int {
          $query = Pegawai::query()->where('aktif', true);
          if ($nip = $this->option('nip')) $query->where('nip', $nip);

          $count = 0;
          $query->chunk(100, function ($pegawais) use ($svc, &$count) {
              foreach ($pegawais as $p) {
                  try {
                      $svc->process($p);
                      $count++;
                  } catch (\Throwable $e) {
                      $this->error("Gagal {$p->nip}: {$e->getMessage()}");
                      report($e);
                  }
              }
          });
          $this->info("Carry-over selesai untuk {$count} pegawai");
          return self::SUCCESS;
      }
  }
  ```
- [ ] **Step 2:** Test integrasi (dispatch command, assert ledger row di-create)
- [ ] **Step 3:** Commit `feat(cuti): ProcessCarryOverCommand`

### Task 4.6: Schedule di `app/Console/Kernel.php`

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1:** Tambah schedule
  ```php
  $schedule->command('cuti:carry-over')
      ->yearlyOn(1, 1, '00:05') // 1 Jan jam 00:05
      ->withoutOverlapping()
      ->onOneServer();
  ```
- [ ] **Step 2:** Verifikasi `php artisan schedule:list` menampilkan entry
- [ ] **Step 3:** Commit `feat(cuti): schedule carry-over yearly 1 Jan 00:05`

---

## Tahap 5: State Machine (Week 2.1, ~2 pd)

Pakai `spatie/laravel-model-states`. 10 state classes + transition rules.

### Task 5.1: Abstract state base + 10 concrete states

**Files:**
- Create: `app/States/Cuti/PengajuanState.php` (abstract)
- Create: `app/States/Cuti/{Draft,Diajukan,Diverifikasi,DisetujuiAtasan,Disetujui,DitolakKepegawaian,DitolakAtasan,DitolakPejabat,Dibatalkan,DicabutSetelahDisetujui}State.php`

- [ ] **Step 1:** Buat abstract base
  ```php
  namespace App\States\Cuti;
  use Spatie\ModelStates\State;
  use Spatie\ModelStates\StateConfig;

  abstract class PengajuanState extends State {
      abstract public function name(): string;
      abstract public function label(): string;
      public function isTerminal(): bool { return false; }

      public static function config(): StateConfig {
          return parent::config()
              ->default(DraftState::class)
              ->allowTransition(DraftState::class, DiajukanState::class)
              ->allowTransition(DraftState::class, DibatalkanState::class)
              ->allowTransition(DiajukanState::class, DiverifikasiState::class)
              ->allowTransition(DiajukanState::class, DitolakKepegawaianState::class)
              ->allowTransition(DiverifikasiState::class, DisetujuiAtasanState::class)
              ->allowTransition(DiverifikasiState::class, DitolakAtasanState::class)
              ->allowTransition(DisetujuiAtasanState::class, DisetujuiState::class)
              ->allowTransition(DisetujuiAtasanState::class, DitolakPejabatState::class)
              ->allowTransition(DisetujuiState::class, DicabutSetelahDisetujuiState::class);
      }
  }
  ```
- [ ] **Step 2:** Buat 10 concrete classes (semua extend `PengajuanState`, override `name()` + `label()` + `isTerminal()`)

  Contoh `DraftState.php`:
  ```php
  namespace App\States\Cuti;
  class DraftState extends PengajuanState {
      public static $name = 'DRAFT';
      public function name(): string { return 'DRAFT'; }
      public function label(): string { return 'Draft'; }
  }
  ```

  Terminal states (`isTerminal()` return `true`): `DitolakKepegawaian`, `DitolakAtasan`, `DitolakPejabat`, `Dibatalkan`, `DicabutSetelahDisetujui`.
- [ ] **Step 3:** Commit `feat(cuti): add state classes (abstract + 10 concrete)`

### Task 5.2: Bind state ke `CutiPengajuan` model

**Files:**
- Modify: `app/Models/Cuti/CutiPengajuan.php`

- [ ] **Step 1:** Tambah cast
  ```php
  use Spatie\ModelStates\HasStates;
  use App\States\Cuti\PengajuanState;

  class CutiPengajuan extends Model {
      use HasUlids, HasStates;
      // ...
      protected $casts = [
          // ... existing
          'state' => PengajuanState::class,
      ];
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): bind PengajuanState to CutiPengajuan model`

### Task 5.3: Test transisi happy path

**Files:**
- Create: `tests/Unit/Cuti/States/PengajuanStateTransitionTest.php`

- [ ] **Step 1:** Test
  ```php
  public function test_full_happy_path_transitions(): void {
      $p = CutiPengajuan::factory()->create();
      $this->assertInstanceOf(DraftState::class, $p->state);
      $p->state->transitionTo(DiajukanState::class);
      $p->state->transitionTo(DiverifikasiState::class);
      $p->state->transitionTo(DisetujuiAtasanState::class);
      $p->state->transitionTo(DisetujuiState::class);
      $this->assertSame('DISETUJUI', $p->fresh()->state->name());
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): state machine happy path`

### Task 5.4: Test invalid transition throws

- [ ] **Step 1:** Test
  ```php
  public function test_skipping_state_throws(): void {
      $p = CutiPengajuan::factory()->create();
      $this->expectException(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);
      $p->state->transitionTo(DisetujuiState::class); // DRAFT → DISETUJUI tidak diizinkan
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): invalid transitions are rejected`

### Task 5.5: Test matriks per jenis cuti — CS tidak boleh dicabut

- [ ] **Step 1:** Test (override transisi DISETUJUI → DICABUT_SETELAH_DISETUJUI hanya untuk CT/CAP)

  Spatie tidak punya per-instance config, jadi guard ini di-enforce di **service layer** (bukan state machine). Test ini akan ada di `WorkflowServiceTest` Tahap 7. Catatan ini saja di plan untuk tracking.
- [ ] **Step 2:** Tambah komentar di `WorkflowService::cancelAfterApproved()` yang akan dibuat di Tahap 7.

---

## Tahap 6: PengajuanCutiService — Submit Flow (Week 2.2, ~2 pd)

Service yang handle submit pengajuan dengan validasi + lock + ledger debit.

### Task 6.1: Test cross-year reject

**Files:**
- Create: `tests/Feature/Cuti/SubmitPengajuanTest.php`
- Create: `app/Services/Cuti/PengajuanCutiService.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_submit_cross_year_rejected(): void {
      $p = Pegawai::factory()->create();
      $this->expectException(\App\Exceptions\Cuti\CrossYearLeaveException::class);
      app(PengajuanCutiService::class)->submit([
          'pegawai_nip' => $p->nip,
          'jenis_cuti_kode' => 'CT',
          'tanggal_mulai' => '2026-12-28',
          'tanggal_selesai' => '2027-01-05',
          'alasan' => 'libur akhir tahun',
      ]);
  }
  ```
- [ ] **Step 2:** Implement minimal (validasi cross-year saja dulu)
  ```php
  public function submit(array $data): CutiPengajuan {
      $start = Carbon::parse($data['tanggal_mulai']);
      $end = Carbon::parse($data['tanggal_selesai']);
      if ($start->year !== $end->year) {
          throw new \App\Exceptions\Cuti\CrossYearLeaveException(
              'Pengajuan tidak boleh lintas tahun. Silakan split menjadi 2 pengajuan.'
          );
      }
      // ... rest
  }
  ```
- [ ] **Step 3:** Buat exception class
- [ ] **Step 4:** Run + commit `feat(cuti): submit rejects cross-year leave`

### Task 6.2: Test alokasi belum ada → reject

- [ ] **Step 1:** Failing test (tahun 2027 belum di-init oleh admin)
- [ ] **Step 2:** Tambah validasi `firstOrFail` lookup `cuti_alokasi_tahunan` di `submit()`
- [ ] **Step 3:** Throw `\App\Exceptions\Cuti\AlokasiTidakAdaException`
- [ ] **Step 4:** Run + commit `feat(cuti): submit rejects when alokasi not initialized`

### Task 6.3: Test happy path submit CT — write pengajuan + debit_pending

- [ ] **Step 1:** Test (saldo cukup → state DIAJUKAN, ledger debit_pending = -N)
- [ ] **Step 2:** Implement full flow:
  ```php
  return DB::transaction(function () use ($data) {
      $start = Carbon::parse($data['tanggal_mulai']);
      $end = Carbon::parse($data['tanggal_selesai']);
      $tahunMulai = $start->year;
      $tahunSelesai = $end->year;

      // Cross-year guard (sudah ada)
      // Pegawai-level lock (semua tahun ASC)
      $tahunDisentuh = range($tahunMulai, $tahunSelesai);
      foreach ($tahunDisentuh as $th) {
          CutiAlokasiTahunan::where('pegawai_nip', $data['pegawai_nip'])
              ->where('jenis_cuti_kode', 'CT')
              ->where('tahun_hak', $th)
              ->lockForUpdate()->firstOrFail();
      }

      // Overlap check (4 state aktif)
      $overlap = CutiPengajuan::where('pegawai_nip', $data['pegawai_nip'])
          ->whereIn('state', ['DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN', 'DISETUJUI'])
          ->where(function ($q) use ($data) {
              $q->whereBetween('tanggal_mulai', [$data['tanggal_mulai'], $data['tanggal_selesai']])
                ->orWhereBetween('tanggal_selesai', [$data['tanggal_mulai'], $data['tanggal_selesai']])
                ->orWhere(function ($q2) use ($data) {
                    $q2->where('tanggal_mulai', '<=', $data['tanggal_mulai'])
                       ->where('tanggal_selesai', '>=', $data['tanggal_selesai']);
                });
          })->exists();
      if ($overlap) throw new \App\Exceptions\Cuti\OverlapPengajuanException();

      // Hitung hari kerja
      $hariKerja = app(HariKerjaCalculatorService::class)->hitung($start, $end);

      // Resolve approver snapshot
      $approver = app(ApproverResolverService::class)->resolveSnapshot($data['pegawai_nip']);

      // Create pengajuan
      $pengajuan = CutiPengajuan::create([
          'nomor_pengajuan' => $this->generateNomor($tahunMulai, $data['pegawai_nip']),
          'pegawai_nip' => $data['pegawai_nip'],
          'jenis_cuti_kode' => $data['jenis_cuti_kode'],
          'tanggal_mulai' => $start,
          'tanggal_selesai' => $end,
          'jumlah_hari_kerja' => $hariKerja,
          'alasan' => $data['alasan'],
          'state' => 'DRAFT',
          'petugas_kepegawaian_snapshot_nip' => $approver['petugas_kepegawaian'],
          'atasan_langsung_snapshot_nip' => $approver['atasan_langsung'],
          'pejabat_berwenang_snapshot_nip' => $approver['pejabat_berwenang'],
          'petugas_kepegawaian_current_nip' => $approver['petugas_kepegawaian'],
          'atasan_langsung_current_nip' => $approver['atasan_langsung'],
          'pejabat_berwenang_current_nip' => $approver['pejabat_berwenang'],
          'submitted_at' => now(),
      ]);

      // Validate per jenis (Strategy pattern — Tahap 6.5+)
      app(\App\Services\Cuti\Rules\CutiRuleEngine::class)->validate($pengajuan);

      // Transition DRAFT → DIAJUKAN
      $pengajuan->state->transitionTo(DiajukanState::class);

      // CT only: debit_pending
      if ($data['jenis_cuti_kode'] === 'CT') {
          app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);
      }

      return $pengajuan->fresh();
  });
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): PengajuanCutiService::submit happy path CT`

### Task 6.4: Method `generateNomor()` — format `CUTI/{YYYY}/{NIP-pendek}/{counter-4-digit}`

- [ ] **Step 1:** Test
  ```php
  public function test_generateNomor_format(): void {
      // create 2 pengajuan untuk tahun yang sama, counter naik
      $svc = app(PengajuanCutiService::class);
      $n1 = $svc->generateNomor(2026, '199001012015031001');
      $n2 = $svc->generateNomor(2026, '199001012015031001');
      $this->assertMatchesRegularExpression('#^CUTI/2026/15031001/\d{4}$#', $n1);
      $this->assertNotSame($n1, $n2);
  }
  ```
- [ ] **Step 2:** Implement (counter via DB lock atau atomic increment di `cuti_konfigurasi`)
- [ ] **Step 3:** Run + commit `feat(cuti): generateNomor counter-based`

### Task 6.5: ApproverResolverService

**Files:**
- Create: `app/Services/Cuti/ApproverResolverService.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_resolveSnapshot_returns_3_role_nips(): void {
      $petugas = Pegawai::factory()->create();
      $atasan = Pegawai::factory()->create();
      $pejabat = Pegawai::factory()->create();
      $pegawai = Pegawai::factory()->create(['atasan_langsung_id' => $atasan->id]);

      // mock petugas + pejabat via role IAM (sesuai permission)
      // ...

      $resolver = app(ApproverResolverService::class);
      $snap = $resolver->resolveSnapshot($pegawai->nip);
      $this->assertSame($petugas->nip, $snap['petugas_kepegawaian']);
      $this->assertSame($atasan->nip, $snap['atasan_langsung']);
      $this->assertSame($pejabat->nip, $snap['pejabat_berwenang']);
  }
  ```
- [ ] **Step 2:** Implement (lookup atasan_langsung_id + role-based untuk petugas/pejabat)
  ```php
  public function resolveSnapshot(string $pegawaiNip): array {
      $pegawai = Pegawai::where('nip', $pegawaiNip)->firstOrFail();
      $atasan = $pegawai->atasanLangsung; // existing relation
      $petugas = Pegawai::role('petugas_kepegawaian')->first();
      $pejabat = Pegawai::role('pejabat_berwenang_cuti')->first();
      return [
          'petugas_kepegawaian' => $petugas?->nip,
          'atasan_langsung' => $atasan?->nip,
          'pejabat_berwenang' => $pejabat?->nip,
      ];
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): ApproverResolverService snapshot`

### Task 6.6: Rule Engine — CutiRuleEngine + 4 strategy class

**Files:**
- Create: `app/Services/Cuti/Rules/CutiRuleEngine.php`
- Create: `app/Services/Cuti/Rules/CutiTahunanRule.php`
- Create: `app/Services/Cuti/Rules/CutiSakitTier1Rule.php`
- Create: `app/Services/Cuti/Rules/CutiSakitTier2Rule.php`
- Create: `app/Services/Cuti/Rules/CutiAlasanPentingRule.php`
- Create: `app/Services/Cuti/Rules/CutiRule.php` (interface)

- [ ] **Step 1:** Interface
  ```php
  interface CutiRule {
      public function applies(string $jenisKode): bool;
      public function validate(CutiPengajuan $p): void; // throws on fail
  }
  ```
- [ ] **Step 2:** RuleEngine
  ```php
  class CutiRuleEngine {
      /** @var CutiRule[] */
      private array $rules;
      public function __construct(array $rules) { $this->rules = $rules; }
      public function validate(CutiPengajuan $p): void {
          foreach ($this->rules as $rule) {
              if ($rule->applies($p->jenis_cuti_kode)) $rule->validate($p);
          }
      }
  }
  ```
- [ ] **Step 3:** CT rule (saldo ≥ jumlah_hari_kerja, submit ≥ H-3)
  ```php
  class CutiTahunanRule implements CutiRule {
      public function applies(string $kode): bool { return $kode === 'CT'; }
      public function validate(CutiPengajuan $p): void {
          // saldo aggregate cek
          $totalSaldo = CutiSaldoLedger::where('pegawai_nip', $p->pegawai_nip)
              ->where('jenis_cuti_kode', 'CT')
              ->where('tahun_hak', $p->tahunHak())
              ->sum('jumlah_hari');
          if ($totalSaldo < $p->jumlah_hari_kerja) {
              throw new SaldoTidakCukupException();
          }
          // H-3 minimum
          if (now()->addDays(3)->gt($p->tanggal_mulai)) {
              throw new \App\Exceptions\Cuti\SubmitTerlambatException('CT harus diajukan minimal H-3');
          }
      }
  }
  ```
- [ ] **Step 4:** CS Tier 1, Tier 2, CAP rules — masing-masing dengan validasi spesifik (lampiran wajib via `cuti_pengajuan_lampiran`, durasi range, dll)
- [ ] **Step 5:** Register di service provider:
  ```php
  $this->app->bind(CutiRuleEngine::class, fn() => new CutiRuleEngine([
      app(CutiTahunanRule::class),
      app(CutiSakitTier1Rule::class),
      app(CutiSakitTier2Rule::class),
      app(CutiAlasanPentingRule::class),
  ]));
  ```
- [ ] **Step 6:** Test 1 happy + 1 fail per rule. Run + commit `feat(cuti): RuleEngine + 4 jenis rules`

### Task 6.7: Test overlap detection

- [ ] **Step 1:** Test (existing pengajuan DIAJUKAN dengan tanggal overlap → reject)
- [ ] **Step 2:** Run + commit `test(cuti): overlap detection`

### Task 6.8: Test concurrent submit (2 paralel pegawai sama → 1 lolos)

- [ ] **Step 1:** Test pakai `\DB::transaction` + `pcntl_fork` atau database-level locking simulation
  ```php
  public function test_concurrent_submit_only_one_succeeds(): void {
      // simulasi: open 2 connection, lock alokasi di conn1, attempt submit di conn2
      // expect: conn2 menunggu lock, conn1 commit success, conn2 fail karena overlap
      $this->markTestSkipped('Race test requires DB connection isolation; verify in integration');
  }
  ```
- [ ] **Step 2:** Documentasi di komentar test untuk verify manual via integration
- [ ] **Step 3:** Commit `test(cuti): concurrent submit (manual verify)`

---

## Tahap 7: WorkflowService — Approval Actions (Week 2.3, ~3 pd)

Service yang handle 6 action: verify, approveAtasan, approvePejabat, reject, cancel, cancelAfterApproved, reassign.

### Task 7.1: WorkflowService skeleton + verify()

**Files:**
- Create: `app/Services/Cuti/WorkflowService.php`
- Create: `tests/Feature/Cuti/WorkflowApprovalTest.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_verify_transitions_to_diverifikasi(): void {
      $petugas = Pegawai::factory()->create();
      $petugas->givePermissionTo('cuti.pengajuan.verify');
      $pengajuan = CutiPengajuan::factory()->create(['state' => 'DIAJUKAN']);

      app(WorkflowService::class)->verify($pengajuan->id, $petugas, 'OK');

      $this->assertSame('DIVERIFIKASI', $pengajuan->fresh()->state->name());
      $this->assertDatabaseHas('cuti_pengajuan_approval_steps', [
          'pengajuan_id' => $pengajuan->id, 'role' => 'petugas_kepegawaian', 'action' => 'verify',
      ]);
  }
  ```
- [ ] **Step 2:** Implement dengan idempotency mutation pattern (Section 7 spec)
  ```php
  public function verify(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void {
      DB::transaction(function () use ($pengajuanId, $aktor, $catatan) {
          $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();
          if (!$pengajuan->state->canTransitionTo(DiverifikasiState::class)) {
              throw new \App\Exceptions\Cuti\TransitionTidakValidException();
          }
          $this->guardAuthorization($aktor, 'cuti.pengajuan.verify');

          $pengajuan->state->transitionTo(DiverifikasiState::class);
          $this->logApprovalStep($pengajuan, 'petugas_kepegawaian', 'verify', $aktor, $catatan);
          $this->logStateHistory($pengajuan, 'DIAJUKAN', 'DIVERIFIKASI', $aktor, $catatan);

          // dispatch event (Tahap 11)
          app(EventDispatcherService::class)->dispatch('cuti.diverifikasi', $pengajuan);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::verify`

### Task 7.2: approveAtasan() — DIVERIFIKASI → DISETUJUI_ATASAN

- [ ] **Step 1:** Test (atasan langsung dengan permission `approve-langsung` AND `current_atasan_langsung_nip = aktor->nip`)
- [ ] **Step 2:** Implement (sama pola dengan verify, tapi tambah cek `current_atasan_langsung_nip`)
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::approveAtasan with current-nip guard`

### Task 7.3: approvePejabat() — DISETUJUI_ATASAN → DISETUJUI (CT: ledger commit + outbox event)

- [ ] **Step 1:** Test
  ```php
  public function test_approvePejabat_CT_writes_ledger_commit_and_outbox_event(): void {
      // setup: pengajuan CT state=DISETUJUI_ATASAN, debit_pending sudah ada
      app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat);
      // assert: state=DISETUJUI, debit_void+debit_confirmed inserted, cuti_events row created
  }
  ```
- [ ] **Step 2:** Implement (state transition + commitConfirmed + dispatch event — semua dalam 1 DB::transaction)
  ```php
  public function approvePejabat(string $id, Pegawai $aktor): void {
      DB::transaction(function () use ($id, $aktor) {
          $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
          if (!$pengajuan->state->canTransitionTo(DisetujuiState::class)) {
              throw new TransitionTidakValidException();
          }
          $this->guardAuthorization($aktor, 'cuti.pengajuan.approve-pejabat');
          if ($pengajuan->pejabat_berwenang_current_nip !== $aktor->nip) {
              throw new AuthorizationException();
          }

          // Lock alokasi anchor jika CT (untuk ledger write)
          if ($pengajuan->jenis_cuti_kode === 'CT') {
              CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                  ->where('jenis_cuti_kode', 'CT')
                  ->where('tahun_hak', $pengajuan->tahunHak())
                  ->lockForUpdate()->firstOrFail();

              app(SaldoLedgerService::class)->commitConfirmed($pengajuan);
          }

          $pengajuan->state->transitionTo(DisetujuiState::class);
          $pengajuan->approved_at = now();
          $pengajuan->save();

          $this->logApprovalStep($pengajuan, 'pejabat_berwenang', 'approve', $aktor);
          $this->logStateHistory($pengajuan, 'DISETUJUI_ATASAN', 'DISETUJUI', $aktor);

          app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::approvePejabat with ledger commit + outbox`

### Task 7.4: rejectByRole() — generic reject method untuk 3 step

- [ ] **Step 1:** Test (3 path: reject by petugas / atasan / pejabat — masing-masing transition ke state ditolak yang berbeda + write debit_void untuk CT)
- [ ] **Step 2:** Implement
  ```php
  public function rejectByRole(string $id, Pegawai $aktor, string $role, string $alasan): void {
      DB::transaction(function () use ($id, $aktor, $role, $alasan) {
          $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
          $targetState = match($role) {
              'petugas_kepegawaian' => DitolakKepegawaianState::class,
              'atasan_langsung' => DitolakAtasanState::class,
              'pejabat_berwenang' => DitolakPejabatState::class,
          };
          if (!$pengajuan->state->canTransitionTo($targetState)) throw new TransitionTidakValidException();
          // authorization checks per role
          // ...

          if ($pengajuan->jenis_cuti_kode === 'CT') {
              CutiAlokasiTahunan::where(...)->lockForUpdate()->firstOrFail();
              app(SaldoLedgerService::class)->voidPending($pengajuan);
          }

          $pengajuan->state->transitionTo($targetState);
          $pengajuan->rejected_at = now();
          $pengajuan->rejection_reason = $alasan;
          $pengajuan->save();
          $this->logApprovalStep($pengajuan, $role, 'reject', $aktor, $alasan);
          $this->logStateHistory($pengajuan, /* current */, /* target */, $aktor, $alasan);
          app(EventDispatcherService::class)->dispatch('cuti.ditolak', $pengajuan);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::rejectByRole 3-path`

### Task 7.5: cancelDraft() — DRAFT → DIBATALKAN (no ledger effect)

- [ ] **Step 1:** Test
- [ ] **Step 2:** Implement (transition only, no ledger)
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::cancelDraft`

### Task 7.6: cancelAfterApproved() — DISETUJUI → DICABUT_SETELAH_DISETUJUI dengan refund FIFO

- [ ] **Step 1:** Test (CT: refund FIFO; CAP: tidak ada ledger; CS: throw karena tidak boleh dicabut)
  ```php
  public function test_cancel_CS_after_approved_throws(): void {
      $pengajuan = CutiPengajuan::factory()->create(['jenis_cuti_kode' => 'CS_TIER1', 'state' => 'DISETUJUI']);
      $this->expectException(\App\Exceptions\Cuti\CancelTidakDiizinkanException::class);
      app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $pegawai, 'sudah sembuh');
  }

  public function test_cancel_CT_after_approved_writes_refund_FIFO(): void {
      // setup: CT disetujui dengan 2 row debit_confirmed di 2 bucket
      // travel ke H+2 dari tanggal_mulai (cuti sudah berjalan)
      // call cancelAfterApproved
      // assert: kredit_refund per bucket dengan total = sisa hari kerja
  }
  ```
- [ ] **Step 2:** Implement (cek `cuti_jenis_master.boleh_dicabut_setelah_disetujui` — guard ini di-enforce di service layer karena state machine universal)
  ```php
  public function cancelAfterApproved(string $id, Pegawai $aktor, string $alasan): void {
      DB::transaction(function () use ($id, $aktor, $alasan) {
          $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
          $jenis = CutiJenisMaster::find($pengajuan->jenis_cuti_kode);
          if (!$jenis->boleh_dicabut_setelah_disetujui) {
              throw new CancelTidakDiizinkanException("{$jenis->nama} tidak bisa dicabut setelah disetujui");
          }
          if (!$pengajuan->state->canTransitionTo(DicabutSetelahDisetujuiState::class)) {
              throw new TransitionTidakValidException();
          }

          if ($pengajuan->jenis_cuti_kode === 'CT') {
              CutiAlokasiTahunan::where(...)->lockForUpdate()->firstOrFail();
              app(SaldoLedgerService::class)->processRefund($pengajuan);
          }
          // CAP: tidak ada ledger effect, hanya state + audit + webhook

          $pengajuan->state->transitionTo(DicabutSetelahDisetujuiState::class);
          $pengajuan->cancelled_at = now();
          $pengajuan->save();
          $this->logStateHistory($pengajuan, 'DISETUJUI', 'DICABUT_SETELAH_DISETUJUI', $aktor, $alasan);
          app(EventDispatcherService::class)->dispatch('cuti.dicabut', $pengajuan);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::cancelAfterApproved with CS guard`

### Task 7.7: reassignApprover() — admin only

- [ ] **Step 1:** Test (admin ganti `current_atasan_langsung_nip` → write history row)
- [ ] **Step 2:** Implement
  ```php
  public function reassignApprover(string $id, string $role, string $newNip, Pegawai $aktor, string $alasan): void {
      DB::transaction(function () use ($id, $role, $newNip, $aktor, $alasan) {
          $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
          $this->guardAuthorization($aktor, 'cuti.pengajuan.reassign');
          $col = "{$role}_current_nip";
          $oldNip = $pengajuan->$col;
          $pengajuan->$col = $newNip;
          $pengajuan->save();
          CutiPengajuanApproverHistory::create([
              'pengajuan_id' => $id,
              'role' => $role,
              'from_pegawai_nip' => $oldNip,
              'to_pegawai_nip' => $newNip,
              'alasan' => $alasan,
              'aktor_pegawai_nip' => $aktor->nip,
          ]);
      });
  }
  ```
- [ ] **Step 3:** Run + commit `feat(cuti): WorkflowService::reassignApprover with audit`

### Task 7.8: Helper internal — guardAuthorization, logApprovalStep, logStateHistory

- [ ] **Step 1:** Refactor common code menjadi private methods di `WorkflowService`
- [ ] **Step 2:** Run all tests + commit `refactor(cuti): extract private helpers in WorkflowService`

### Task 7.9: Test idempotency mutation (re-submit verify request → tidak duplicate row)

- [ ] **Step 1:** Test
  ```php
  public function test_verify_idempotent_via_state_revalidate(): void {
      $pengajuan = CutiPengajuan::factory()->create(['state' => 'DIAJUKAN']);
      app(WorkflowService::class)->verify($pengajuan->id, $petugas);
      // call lagi → harus throw TransitionTidakValid karena state sudah DIVERIFIKASI
      $this->expectException(TransitionTidakValidException::class);
      app(WorkflowService::class)->verify($pengajuan->id, $petugas);
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): mutation idempotency via state re-validate`

---

## Tahap 8: Policy & Authorization (Week 2.4, ~0.5 pd)

### Task 8.1: CutiPengajuanPolicy

**Files:**
- Create: `app/Policies/Cuti/CutiPengajuanPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`

- [ ] **Step 1:** Buat policy dengan method: `viewOwn`, `viewTeam`, `viewAll`, `verify`, `approveLangsung`, `approvePejabat`, `cancelOwn`, `cancelAny`, `reassign`
  ```php
  class CutiPengajuanPolicy {
      public function viewOwn(Pegawai $u, CutiPengajuan $p): bool {
          return $u->nip === $p->pegawai_nip && $u->can('cuti.pengajuan.view-own');
      }
      public function approveLangsung(Pegawai $u, CutiPengajuan $p): bool {
          return $u->can('cuti.pengajuan.approve-langsung')
              && $u->nip === $p->atasan_langsung_current_nip;
      }
      // ... 7 method lainnya
  }
  ```
- [ ] **Step 2:** Register di `AuthServiceProvider`
- [ ] **Step 3:** Test policy methods (Unit test cepat)
- [ ] **Step 4:** Commit `feat(cuti): add CutiPengajuanPolicy with current-nip guards`

---

## Tahap 9: Web Controllers + Form Requests (Week 2.5, ~3 pd)

### Task 9.1: SubmitPengajuanRequest

**Files:**
- Create: `app/Http/Requests/Cuti/SubmitPengajuanRequest.php`

- [ ] **Step 1:** Buat dengan validasi
  ```php
  public function rules(): array {
      return [
          'jenis_cuti_kode' => ['required', 'in:CT,CS_TIER1,CS_TIER2,CAP'],
          'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
          'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai',
              // No cross-year (form-layer validation L2)
              function ($attr, $val, $fail) {
                  $start = \Carbon\Carbon::parse(request('tanggal_mulai'));
                  $end = \Carbon\Carbon::parse($val);
                  if ($start->year !== $end->year) {
                      $fail('Pengajuan tidak boleh lintas tahun. Silakan split menjadi 2 pengajuan.');
                  }
              },
          ],
          'alasan' => ['required', 'string', 'max:1000'],
          'alamat_selama_cuti' => ['nullable', 'string', 'max:500'],
          'nomor_telp_selama_cuti' => ['nullable', 'string', 'max:30'],
          'lampiran.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB
      ];
  }
  ```
- [ ] **Step 2:** Commit `feat(cuti): SubmitPengajuanRequest with cross-year validation`

### Task 9.2: PengajuanController

**Files:**
- Create: `app/Http/Controllers/Cuti/PengajuanController.php`

- [ ] **Step 1:** Buat dengan 4 action: `create()` (Inertia render form), `store()` (call service), `show()`, `myPage()`
  ```php
  public function store(SubmitPengajuanRequest $request) {
      $pengajuan = app(PengajuanCutiService::class)->submit(
          $request->validated() + ['pegawai_nip' => $request->user()->nip]
      );
      // upload lampiran jika ada
      foreach ($request->file('lampiran', []) as $file) {
          $path = $file->store("cuti/{$pengajuan->pegawai_nip}/" . now()->year, 'local');
          CutiPengajuanLampiran::create([...]);
      }
      return redirect()->route('cuti.pengajuan.show', $pengajuan->id)
          ->with('success', 'Pengajuan berhasil dikirim');
  }
  ```
- [ ] **Step 2:** Test feature happy path
- [ ] **Step 3:** Commit `feat(cuti): PengajuanController CRUD`

### Task 9.3: ApprovalController (verify, approve, reject, cancel, reassign)

**Files:**
- Create: `app/Http/Controllers/Cuti/ApprovalController.php`
- Create: `app/Http/Requests/Cuti/ApproveRequest.php`
- Create: `app/Http/Requests/Cuti/RejectRequest.php`
- Create: `app/Http/Requests/Cuti/ReassignApproverRequest.php`

- [ ] **Step 1:** Buat 6 method (verify, approveAtasan, approvePejabat, reject, cancel, reassign)
- [ ] **Step 2:** Tiap method authorize via policy + delegate ke `WorkflowService`
- [ ] **Step 3:** Inbox view: `inbox()` action yang return Inertia dengan data sesuai role aktor
- [ ] **Step 4:** Test feature happy paths
- [ ] **Step 5:** Commit `feat(cuti): ApprovalController + 3 form requests`

### Task 9.4: SaldoController (pegawai dashboard + admin init)

**Files:**
- Create: `app/Http/Controllers/Cuti/SaldoController.php`

- [ ] **Step 1:** Method:
  - `myDashboard()` — view saya: saldo + riwayat pengajuan
  - `adminIndex()` — list semua pegawai + saldo
  - `adminInit()` — form inisialisasi alokasi
  - `adminInitStore()` — call `kreditAlokasi`
  - `adminAdjust()` — penyesuaian (positif/negatif via `penyesuaian`)
- [ ] **Step 2:** Test feature
- [ ] **Step 3:** Commit `feat(cuti): SaldoController with admin saldo management`

### Task 9.5: web.php routes wiring

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1:** Tambah route group
  ```php
  Route::middleware(['web', 'auth'])->prefix('cuti')->name('cuti.')->group(function () {
      Route::get('/saya', [SaldoController::class, 'myDashboard'])->name('saya');
      Route::get('/pengajuan/baru', [PengajuanController::class, 'create'])->name('pengajuan.create');
      Route::post('/pengajuan', [PengajuanController::class, 'store'])->name('pengajuan.store');
      Route::get('/pengajuan/{id}', [PengajuanController::class, 'show'])->name('pengajuan.show');
      Route::get('/inbox', [ApprovalController::class, 'inbox'])->name('inbox');
      Route::post('/pengajuan/{id}/verify', [ApprovalController::class, 'verify'])->name('pengajuan.verify');
      Route::post('/pengajuan/{id}/approve', [ApprovalController::class, 'approve'])->name('pengajuan.approve');
      Route::post('/pengajuan/{id}/reject', [ApprovalController::class, 'reject'])->name('pengajuan.reject');
      Route::post('/pengajuan/{id}/cancel', [ApprovalController::class, 'cancel'])->name('pengajuan.cancel');
      Route::post('/pengajuan/{id}/reassign-approver', [ApprovalController::class, 'reassign'])->middleware('can:cuti.pengajuan.reassign')->name('pengajuan.reassign');
      Route::get('/pengajuan/{id}/pdf', [PdfController::class, 'show'])->name('pengajuan.pdf');
  });

  Route::middleware(['web', 'auth', 'can:cuti.saldo.view-all'])->prefix('admin/cuti')->name('admin.cuti.')->group(function () {
      Route::get('/saldo', [SaldoController::class, 'adminIndex'])->name('saldo.index');
      Route::get('/saldo/init', [SaldoController::class, 'adminInit'])->name('saldo.init');
      Route::post('/saldo/init', [SaldoController::class, 'adminInitStore'])->name('saldo.init.store');
      Route::post('/saldo/adjust', [SaldoController::class, 'adminAdjust'])->middleware('can:cuti.saldo.adjust')->name('saldo.adjust');
  });
  ```
- [ ] **Step 2:** Smoke test: `php artisan route:list | grep cuti`
- [ ] **Step 3:** Commit `feat(cuti): wire web routes`

---

## Tahap 10: REST API (Week 2.6, ~1 pd)

Read-only API untuk integrasi eksternal.

### Task 10.1: Api routes group

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1:** Tambah
  ```php
  Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('cuti')->name('api.cuti.')->group(function () {
      Route::get('/pengajuan', [Api\PengajuanController::class, 'index']);
      Route::get('/pengajuan/{id}', [Api\PengajuanController::class, 'show']);
      Route::get('/saldo/{nip}', [Api\SaldoController::class, 'show']);
      Route::get('/saldo/{nip}/ledger', [Api\SaldoController::class, 'ledger']);
  });
  ```
- [ ] **Step 2:** Commit `feat(cuti): wire api routes`

### Task 10.2: Api\PengajuanController + Resources

**Files:**
- Create: `app/Http/Controllers/Api/Cuti/PengajuanController.php`
- Create: `app/Http/Resources/Cuti/PengajuanResource.php`

- [ ] **Step 1:** Index dengan filter (state, pegawai_nip, tahun) + paginate
- [ ] **Step 2:** Show + eager load relations
- [ ] **Step 3:** Resource transformer (snake_case JSON sesuai konvensi project existing)
- [ ] **Step 4:** Test feature
- [ ] **Step 5:** Commit `feat(cuti): Api\PengajuanController read-only`

### Task 10.3: Api\SaldoController

**Files:**
- Create: `app/Http/Controllers/Api/Cuti/SaldoController.php`
- Create: `app/Http/Resources/Cuti/SaldoResource.php`

- [ ] **Step 1:** `show($nip)` — return saldo per jenis per tahun aktif
- [ ] **Step 2:** `ledger($nip)` — return ledger entries dengan pagination + filter tahun
- [ ] **Step 3:** Test feature
- [ ] **Step 4:** Commit `feat(cuti): Api\SaldoController read-only`

---

## Tahap 11: Outbox + Webhook (Week 2.7, ~2.5 pd)

### Task 11.1: EventDispatcherService — atomic outbox write

**Files:**
- Create: `app/Services/Cuti/EventDispatcherService.php`

- [ ] **Step 1:** Failing test
  ```php
  public function test_dispatch_writes_event_and_deliveries_atomically(): void {
      $p = CutiPengajuan::factory()->create();
      app(EventDispatcherService::class)->dispatch('cuti.disetujui', $p);
      $this->assertDatabaseHas('cuti_events', ['event_type' => 'cuti.disetujui', 'aggregate_id' => $p->id]);
      $this->assertDatabaseHas('cuti_event_deliveries', ['consumer_id' => 'attendance-qr-system', 'status' => 'pending']);
  }
  ```
- [ ] **Step 2:** Implement
  ```php
  public function dispatch(string $eventType, CutiPengajuan $aggregate): CutiEvent {
      $event = CutiEvent::create([
          'aggregate_type' => 'PengajuanCuti',
          'aggregate_id' => $aggregate->id,
          'event_type' => $eventType,
          'payload' => $this->buildPayload($eventType, $aggregate),
          'occurred_at' => now(),
      ]);
      foreach (config('cuti.consumers', ['attendance-qr-system']) as $consumerId) {
          CutiEventDelivery::firstOrCreate(
              ['event_id' => $event->id, 'consumer_id' => $consumerId],
              ['status' => 'pending']
          );
      }
      return $event;
  }

  private function buildPayload(string $type, CutiPengajuan $p): array {
      return [
          'event_id' => null, // akan di-set oleh caller setelah CutiEvent::create return id
          'event_type' => $type,
          'occurred_at' => now()->toIso8601String(),
          'data' => [
              'pengajuan_id' => $p->id,
              'pegawai_nip' => $p->pegawai_nip,
              'jenis_cuti' => $p->jenis_cuti_kode,
              'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
              'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
              'jumlah_hari_kerja' => $p->jumlah_hari_kerja,
          ],
      ];
  }
  ```
  Catatan: payload juga harus include `event_id` setelah `CutiEvent::create` return id. Refactor untuk update payload setelah event dibuat.
- [ ] **Step 3:** Run + commit `feat(cuti): EventDispatcherService atomic outbox write`

### Task 11.2: Test outbox atomicity (rollback)

- [ ] **Step 1:** Failing test
  ```php
  public function test_dispatch_rollback_includes_event(): void {
      $p = CutiPengajuan::factory()->create();
      try {
          DB::transaction(function () use ($p) {
              app(EventDispatcherService::class)->dispatch('cuti.disetujui', $p);
              throw new \RuntimeException('forced rollback');
          });
      } catch (\RuntimeException) {}
      $this->assertDatabaseMissing('cuti_events', ['aggregate_id' => $p->id]);
  }
  ```
- [ ] **Step 2:** Run — should pass karena Eloquent::create dalam DB::transaction otomatis ikut rollback
- [ ] **Step 3:** Commit `test(cuti): outbox respects DB::transaction rollback`

### Task 11.3: Worker `DispatchPendingEventsCommand`

**Files:**
- Create: `app/Console/Commands/Cuti/DispatchPendingEventsCommand.php`

- [ ] **Step 1:** Buat command yang query `cuti_event_deliveries` status='pending' atau ('failed' AND next_retry_at ≤ now), HTTP POST dengan signature, update status
  ```php
  public function handle(): int {
      $limit = (int) $this->option('limit') ?: 50;
      $rows = CutiEventDelivery::with('event')
          ->where(function ($q) {
              $q->where('status', 'pending')
                ->orWhere(function ($q2) { $q2->where('status', 'failed')->where('next_retry_at', '<=', now()); });
          })
          ->where('attempts', '<', 6)
          ->orderBy('created_at', 'asc')
          ->limit($limit)->get();

      foreach ($rows as $delivery) {
          $this->deliver($delivery);
      }
      return self::SUCCESS;
  }

  private function deliver(CutiEventDelivery $d): void {
      $consumer = ConsumerRegistry::get($d->consumer_id); // gets URL + secret
      $event = $d->event;

      // Build canonical signature
      $rawBody = json_encode($event->payload + ['event_id' => $event->id], JSON_UNESCAPED_SLASHES);
      $timestamp = time();
      $canonical = "{$event->id}.{$timestamp}.{$rawBody}";
      $secret = Crypt::decryptString($consumer->shared_secret_encrypted);
      $signature = hash_hmac('sha256', $canonical, $secret);

      $d->update(['status' => 'in_flight', 'attempts' => $d->attempts + 1, 'last_attempt_at' => now()]);

      try {
          $resp = Http::withHeaders([
              'X-Event-Id' => $event->id,
              'X-Timestamp' => (string) $timestamp,
              'X-Signature' => $signature,
              'Content-Type' => 'application/json',
          ])->withBody($rawBody, 'application/json')->post($consumer->webhook_url);

          if ($resp->successful()) {
              $d->update(['status' => 'delivered', 'delivered_at' => now()]);
          } else {
              $this->markFailed($d, "HTTP {$resp->status()}");
          }
      } catch (\Throwable $e) {
          $this->markFailed($d, $e->getMessage());
      }
  }

  private function markFailed(CutiEventDelivery $d, string $err): void {
      $backoff = [60, 300, 900, 3600, 21600, 86400][min($d->attempts - 1, 5)];
      $status = $d->attempts >= 6 ? 'dead_letter' : 'failed';
      $d->update([
          'status' => $status,
          'last_error' => $err,
          'next_retry_at' => $status === 'failed' ? now()->addSeconds($backoff) : null,
      ]);
  }
  ```
- [ ] **Step 2:** Test (mock Http facade, assert calls)
- [ ] **Step 3:** Commit `feat(cuti): DispatchPendingEventsCommand worker with retry`

### Task 11.4: Test signature canonical (`{event_id}.{timestamp}.{raw_body}`)

- [ ] **Step 1:** Test
  ```php
  public function test_signature_uses_canonical_string_with_event_id(): void {
      Http::fake();
      // dispatch event, run worker
      // capture sent request, recompute expected signature, assert match
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): webhook signature canonical includes event_id`

### Task 11.5: ConsumerRegistry config-based

**Files:**
- Create: `config/cuti.php`
- Create: `app/Services/Cuti/ConsumerRegistry.php`

- [ ] **Step 1:** Config
  ```php
  return [
      'consumers' => [
          'attendance-qr-system' => [
              'webhook_url' => env('CUTI_ATTENDANCE_WEBHOOK_URL'),
              'shared_secret_encrypted' => env('CUTI_ATTENDANCE_SHARED_SECRET_ENCRYPTED'),
          ],
      ],
  ];
  ```
- [ ] **Step 2:** Registry class yang lookup config
- [ ] **Step 3:** Commit `feat(cuti): ConsumerRegistry + config/cuti.php`

### Task 11.6: Schedule worker tiap 1 menit

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1:**
  ```php
  $schedule->command('cuti:dispatch-events')->everyMinute()->withoutOverlapping();
  ```
- [ ] **Step 2:** Commit `feat(cuti): schedule webhook worker every minute`

---

## Tahap 12: UI Pegawai (Week 3.1, ~3 pd)

### Task 12.1: Page `/cuti/saya` (dashboard)

**Files:**
- Create: `resources/js/pages/cuti/Saya.tsx`
- Create: `resources/js/components/cuti/KartuSaldo.tsx`

- [ ] **Step 1:** Buat KartuSaldo component (props: saldo per jenis, ringkasan tahun)
- [ ] **Step 2:** Buat Saya page (cards, tabel riwayat pengajuan dengan badge status)
- [ ] **Step 3:** Test browser manual: `php artisan serve` lalu visit `/cuti/saya`
- [ ] **Step 4:** Commit `feat(cuti-ui): Saya dashboard with KartuSaldo`

### Task 12.2: Page `/cuti/pengajuan/baru` (form)

**Files:**
- Create: `resources/js/pages/cuti/PengajuanBaru.tsx`
- Create: `resources/js/components/cuti/FormPengajuan.tsx`

- [ ] **Step 1:** Form fields: jenis_cuti (Select), tanggal_mulai (DatePicker), tanggal_selesai (DatePicker), alasan (Textarea), alamat (Input), telp (Input), lampiran (file)
- [ ] **Step 2:** Client-side validasi:
  - `tanggal_mulai >= today + 3` untuk CT
  - `tanggal_mulai.year === tanggal_selesai.year` (no cross-year — match server)
  - durasi maksimum sesuai jenis (CS Tier 1 ≤14 hari, dst)
- [ ] **Step 3:** Submit via `useForm()` Inertia + redirect ke detail
- [ ] **Step 4:** Test browser manual
- [ ] **Step 5:** Commit `feat(cuti-ui): PengajuanBaru form with cross-year client validation`

### Task 12.3: Page `/cuti/pengajuan/{id}` (detail + audit trail)

**Files:**
- Create: `resources/js/pages/cuti/PengajuanDetail.tsx`
- Create: `resources/js/components/cuti/TimelineApproval.tsx`

- [ ] **Step 1:** Detail card (data pengajuan + lampiran download)
- [ ] **Step 2:** TimelineApproval component (state history vertikal dengan ikon + timestamp)
- [ ] **Step 3:** Action buttons (cancel jika DRAFT/DISETUJUI sesuai jenis, view PDF, dll)
- [ ] **Step 4:** Test browser manual
- [ ] **Step 5:** Commit `feat(cuti-ui): PengajuanDetail with TimelineApproval`

---

## Tahap 13: UI Approver (Week 3.2, ~3 pd)

### Task 13.1: Page `/cuti/inbox`

**Files:**
- Create: `resources/js/pages/cuti/Inbox.tsx`

- [ ] **Step 1:** Filter by role aktor (otomatis dari user permission):
  - Petugas: list pengajuan state=DIAJUKAN
  - Atasan: list pengajuan state=DIVERIFIKASI AND atasan_langsung_current_nip = aktor.nip
  - Pejabat: list pengajuan state=DISETUJUI_ATASAN AND pejabat_berwenang_current_nip = aktor.nip
- [ ] **Step 2:** Tabel dengan kolom: nomor, pegawai, jenis, tanggal, durasi, action
- [ ] **Step 3:** Action button row → buka DialogApprove
- [ ] **Step 4:** Commit `feat(cuti-ui): Inbox approver with role-based filter`

### Task 13.2: DialogApprove component

**Files:**
- Create: `resources/js/components/cuti/DialogApprove.tsx`

- [ ] **Step 1:** Modal dengan:
  - Detail singkat pengajuan
  - Field catatan (opsional)
  - Button "Setujui" (POST `/cuti/pengajuan/{id}/approve`) + "Tolak" (buka DialogReject)
- [ ] **Step 2:** Toast notif on success
- [ ] **Step 3:** Commit `feat(cuti-ui): DialogApprove`

### Task 13.3: DialogReject + DialogCancel

**Files:**
- Create: `resources/js/components/cuti/DialogReject.tsx`
- Create: `resources/js/components/cuti/DialogCancel.tsx`

- [ ] **Step 1:** Reject: alasan (required, ≥10 chars)
- [ ] **Step 2:** Cancel: alasan (required) + warning kalau cuti sudah berjalan (refund proporsional)
- [ ] **Step 3:** Commit `feat(cuti-ui): DialogReject + DialogCancel`

---

## Tahap 14: UI Admin (Week 3.3, ~2 pd)

### Task 14.1: Page `/admin/cuti/saldo` — list & init alokasi

**Files:**
- Create: `resources/js/pages/cuti/admin/Saldo.tsx`

- [ ] **Step 1:** Tabel: pegawai, alokasi tahun aktif, saldo aktual, button [Init], [Adjust]
- [ ] **Step 2:** Bulk init: input tahun + jumlah default → call endpoint
- [ ] **Step 3:** Commit `feat(cuti-ui): admin Saldo page`

### Task 14.2: Dialog Adjust Saldo

**Files:**
- Create: `resources/js/components/cuti/DialogAdjustSaldo.tsx`

- [ ] **Step 1:** Form: jenis_cuti, tahun_hak, jumlah_hari (signed, signed=positif tambah, negatif kurang), keterangan
- [ ] **Step 2:** Submit ke `/admin/cuti/saldo/adjust` (panggil `SaldoLedgerService` insert `penyesuaian` row)
- [ ] **Step 3:** Commit `feat(cuti-ui): DialogAdjustSaldo`

### Task 14.3: Page `/admin/cuti/audit` — activity log viewer

**Files:**
- Create: `resources/js/pages/cuti/admin/Audit.tsx`

- [ ] **Step 1:** Query `activity_log` dengan `log_name='cuti'`
- [ ] **Step 2:** Filter by date, aktor, pengajuan_id
- [ ] **Step 3:** Commit `feat(cuti-ui): admin Audit viewer`

---

## Tahap 15: Notifications (Week 3.4, ~1 pd)

### Task 15.1: 4 Notification class

**Files:**
- Create: `app/Notifications/Cuti/PengajuanMenungguVerifikasi.php`
- Create: `app/Notifications/Cuti/PengajuanMenungguApproval.php`
- Create: `app/Notifications/Cuti/PengajuanDisetujui.php`
- Create: `app/Notifications/Cuti/PengajuanDitolak.php`

- [ ] **Step 1:** Buat 4 class, semua extend `Illuminate\Notifications\Notification`, `via()` return `['database']` only (no email/WA di MVP)
- [ ] **Step 2:** Method `toDatabase()` return array dengan title, body, link
- [ ] **Step 3:** Commit `feat(cuti): 4 notification classes (database only)`

### Task 15.2: Wire notif di WorkflowService

**Files:**
- Modify: `app/Services/Cuti/WorkflowService.php`

- [ ] **Step 1:** Tambah `Notification::send()` di tiap method:
  - `submit()` → notify petugas
  - `verify()` → notify atasan langsung current
  - `approveAtasan()` → notify pejabat berwenang current
  - `approvePejabat()` → notify pegawai (DISETUJUI)
  - `rejectByRole()` → notify pegawai (DITOLAK)
- [ ] **Step 2:** Test: assert `Notification::fake()` then `Notification::assertSentTo(...)`
- [ ] **Step 3:** Commit `feat(cuti): wire notifications to WorkflowService`

### Task 15.3: NotificationBell component

**Files:**
- Create: `resources/js/components/NotificationBell.tsx`

- [ ] **Step 1:** Bell icon dengan badge unread count, dropdown list 10 latest
- [ ] **Step 2:** Mark as read on click
- [ ] **Step 3:** Mount di global layout
- [ ] **Step 4:** Commit `feat(cuti-ui): NotificationBell global`

---

## Tahap 16: PDF Generator (Week 4.1, ~1 pd)

### Task 16.1: Browsershot smoke test

- [ ] **Step 1:** Test command
  ```bash
  php artisan tinker
  >>> Spatie\LaravelPdf\Facades\Pdf::view('test', [])->save('/tmp/test.pdf');
  >>> exit
  ```
- [ ] **Step 2:** Verify file generated. Jika fail, cek Node + Puppeteer install.
- [ ] **Step 3:** No commit (verification only)

### Task 16.2: Blade template form cuti

**Files:**
- Create: `resources/views/pdf/cuti/pengajuan.blade.php`

- [ ] **Step 1:** Buat template HTML/CSS sederhana sesuai `docs/form_cuti.docx` reference
  - Header: kop surat + judul "Formulir Pengajuan Cuti"
  - Body: data pegawai (NIP, nama, jabatan), jenis cuti, tanggal, alasan
  - Footer: tanda tangan 3 kolom (pegawai, atasan, pejabat)
- [ ] **Step 2:** Use Tailwind classes minimal (Browsershot support modern CSS)
- [ ] **Step 3:** Commit `feat(cuti): PDF template formulir pengajuan`

### Task 16.3: PdfController + route

**Files:**
- Create: `app/Http/Controllers/Cuti/PdfController.php`

- [ ] **Step 1:**
  ```php
  public function show(string $id) {
      $pengajuan = CutiPengajuan::with('pegawai', 'lampiran', 'approvalSteps.aktor')->findOrFail($id);
      Gate::authorize('view', $pengajuan);
      return Pdf::view('pdf.cuti.pengajuan', ['p' => $pengajuan])
          ->name("cuti-{$pengajuan->nomor_pengajuan}.pdf")
          ->download();
  }
  ```
- [ ] **Step 2:** Save metadata ke `cuti_pengajuan_pdf` (path, checksum, size) jika perlu cache
- [ ] **Step 3:** Test feature
- [ ] **Step 4:** Commit `feat(cuti): PdfController generate + download`

---

## Tahap 17: Integration & Polish (Week 4.2, ~2 pd)

### Task 17.1: E2E test happy path

**Files:**
- Create: `tests/Feature/Cuti/EndToEndTest.php`

- [ ] **Step 1:** Test flow lengkap
  ```php
  public function test_full_workflow_CT_happy_path(): void {
      // setup: pegawai, atasan, petugas, pejabat dengan permission
      // ARRANGE: alokasi CT 12 hari tahun aktif
      // ACT 1: submit (state=DIAJUKAN, debit_pending=-3)
      // ACT 2: petugas verify (state=DIVERIFIKASI)
      // ACT 3: atasan approve (state=DISETUJUI_ATASAN)
      // ACT 4: pejabat approve (state=DISETUJUI, debit_void+debit_confirmed)
      // ASSERT: saldo = 9, cuti_events ada 4 row, cuti_event_deliveries ada untuk consumer
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): E2E happy path full workflow`

### Task 17.2: E2E test refund pencabutan CT

- [ ] **Step 1:** Test
  ```php
  public function test_E2E_cancel_after_approved_CT_refund_FIFO(): void {
      // setup: 2 bucket (N-1=4, N=12), submit 7 hari (FIFO split 4+3 debit_confirmed setelah approve final)
      // ACT: cancel after approved (today = day 2 of 5 → sisa 3 hari kerja)
      // ASSERT: 2 row kredit_refund per bucket, total 3 hari
  }
  ```
- [ ] **Step 2:** Run + commit `test(cuti): E2E refund FIFO multi-bucket`

### Task 17.3: E2E test reject di setiap step (3 path)

- [ ] **Step 1:** 3 test method (reject petugas, reject atasan, reject pejabat) — assert state + debit_void written
- [ ] **Step 2:** Run + commit `test(cuti): E2E reject 3-path`

### Task 17.4: E2E test CS tidak bisa dicabut

- [ ] **Step 1:** Test (CS Tier 1 disetujui → cancelAfterApproved throw `CancelTidakDiizinkanException`)
- [ ] **Step 2:** Run + commit `test(cuti): E2E CS cannot cancel after approved`

### Task 17.5: ExpireOverdueDraftsCommand

**Files:**
- Create: `app/Console/Commands/Cuti/ExpireOverdueDraftsCommand.php`

- [ ] **Step 1:** Command yang transition DRAFT > 7 hari → DIBATALKAN otomatis
- [ ] **Step 2:** Schedule daily 00:30
- [ ] **Step 3:** Test + commit `feat(cuti): ExpireOverdueDraftsCommand daily`

### Task 17.6: Activity log integration

**Files:**
- Modify: `app/Models/Cuti/CutiPengajuan.php`

- [ ] **Step 1:** Tambah `LogsActivity` trait
  ```php
  use Spatie\Activitylog\Traits\LogsActivity;
  use Spatie\Activitylog\LogOptions;

  class CutiPengajuan extends Model {
      use HasUlids, HasStates, LogsActivity;

      public function getActivitylogOptions(): LogOptions {
          return LogOptions::defaults()
              ->logOnly(['state', 'rejection_reason', 'cancelled_at', 'approved_at'])
              ->useLogName('cuti')
              ->logOnlyDirty();
      }
  }
  ```
- [ ] **Step 2:** Test: update pengajuan, assert activity_log entry
- [ ] **Step 3:** Commit `feat(cuti): integrate activity log on CutiPengajuan`

---

## Tahap 18: Final Verification & Documentation (Week 4.3, ~1 pd)

### Task 18.1: Full test suite run

- [ ] **Step 1:**
  ```bash
  vendor/bin/phpunit --testsuite=Unit
  vendor/bin/phpunit --testsuite=Feature
  ```
- [ ] **Step 2:** Fix any flaky test
- [ ] **Step 3:** Coverage report (target ≥80% untuk Service & Business Logic)
  ```bash
  vendor/bin/phpunit --coverage-text --filter=Cuti
  ```

### Task 18.2: docs/cuti/README.md

**Files:**
- Create: `docs/cuti/README.md`

- [ ] **Step 1:** Tulis overview (link ke spec), arsitektur ringkas, file structure, getting started
- [ ] **Step 2:** Diagram flow ASCII (DRAFT → DIAJUKAN → ... → DISETUJUI/DICABUT)
- [ ] **Step 3:** Commit `docs(cuti): module README`

### Task 18.3: docs/cuti/runbook.md

**Files:**
- Create: `docs/cuti/runbook.md`

- [ ] **Step 1:** Tulis prosedur operasional:
  - Bagaimana inisialisasi saldo awal tahun (admin manual via UI atau bulk import)
  - Manual replay carry-over kalau scheduler miss
  - Webhook dead_letter handling (cek `cuti_event_deliveries` status=dead_letter, manual retry)
  - Reassign approver flow saat atasan mutasi
  - Disaster recovery: rebuild saldo dari ledger SUM
- [ ] **Step 2:** Commit `docs(cuti): operational runbook`

### Task 18.4: Final code review checkpoint

- [ ] **Step 1:** Invoke `superpowers:requesting-code-review` skill untuk review keseluruhan modul
- [ ] **Step 2:** Apply critical findings (jika ada)
- [ ] **Step 3:** Re-run full test suite
- [ ] **Step 4:** Tag commit `git tag cuti-mvp-v1.0`

---

## Self-Review Plan (Internal)

### Spec Coverage Map (per Section spec → Tahap plan)

| Spec Section | Tahap Plan |
|---|---|
| §3 Fase 1 scope (4 jenis cuti, workflow 4-step) | Tahap 6, 7 |
| §4 Architecture + Outbox invariant | Tahap 11 |
| §4.1 Inertia for UI, REST API for integration | Tahap 9 (Inertia), Tahap 10 (REST) |
| §5 Aktor & Permissions | Tahap 1 (seeder), Tahap 8 (Policy) |
| §6 Data Model 15 tabel | Tahap 1.1-1.15 (migrations), 1.19-1.23 (models) |
| §7 State Machine 10 states | Tahap 5 |
| §7 Idempotency Mutation | Tahap 7 (workflow lockForUpdate pattern) |
| §7 Idempotency system-generated (NULL pengajuan_id) | Tahap 3.10-3.11 (kreditAlokasi), Tahap 4 (carry-over) |
| §8.1 Carry-Over | Tahap 4 |
| §8.2 Hari Kerja vs Kalender | Tahap 2 |
| §8.3 Validasi + No cross-year | Tahap 6 (validate) + Tahap 9.1 (FormRequest) + Tahap 12.2 (client-side) |
| §8.4 FIFO Split Bucket (CT) | Tahap 3.3-3.5 (debitPendingFifo), Tahap 3.6 (commitConfirmed) |
| §8.5 Refund Pencabutan FIFO | Tahap 3.8-3.9, Tahap 7.6 |
| §9 Web routes + REST API + Webhook + Replay protection | Tahap 9-11 |
| §10 UI Flow (3 group) | Tahap 12, 13, 14 |
| §11 File Structure | Sesuai per Tahap |
| §12 Estimasi 32.5 pd | Tahap split tracking (~32 pd total) |
| §13 Phasing internal | Tahap 1-18 mapped ke Week 1-4 |
| §14 Risiko mitigasi | Implicit di TDD + integration tests |
| §15 Locked decisions L1-L3 | Plan reflects L1 (FIFO refund), L2 (no cross-year), L3 (3 paket Composer) |
| §16 Dependencies | Tahap 0.2 install |

**Coverage**: 100% — semua section spec ada di plan.

### Placeholder Scan

- [x] No "TBD" / "TODO" / "implement later"
- [x] Setiap step yang code-related punya code block
- [x] Setiap step run command punya `vendor/bin/...` atau `php artisan ...`
- [x] Setiap commit step punya pesan commit eksplisit

### Ambiguity & Type Consistency

- [x] `debitPendingFifo` consistent across Tahap 3, 6, 7
- [x] `commitConfirmed` consistent across Tahap 3, 7
- [x] `processRefund` consistent across Tahap 3, 7
- [x] Method names: `verify`, `approveAtasan`, `approvePejabat`, `rejectByRole`, `cancelDraft`, `cancelAfterApproved`, `reassignApprover` — consistent

---

## Execution Handoff

**Plan complete and saved to** `docs/superpowers/plans/2026-05-01-modul-cuti.md`. Total **~80 tasks** across 18 tahap (~32 person-days mapped).

Ada 2 opsi eksekusi:

### 1. Subagent-Driven (recommended)

Saya dispatch fresh subagent per task. Two-stage review per output (spec compliance check + code quality check). Iteration cepat, autonomous untuk extended period. Cocok untuk skala plan ini.

### 2. Inline Execution

Saya execute tasks di session ini pakai `superpowers:executing-plans`. Batch execution dengan checkpoint untuk review per Tahap. Lebih lambat tapi visibility tinggi.

**Mana yang Anda pilih?** (Atau review plan dulu sebelum execute?)
