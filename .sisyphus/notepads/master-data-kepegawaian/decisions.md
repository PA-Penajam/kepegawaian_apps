# Decisions — master-data-kepegawaian

## [2026-03-15] Initialized

- No CRUD pages untuk reference tables — seed only
- No Spatie Permission — simple enum Role
- No chart library — shadcn/ui progress bars untuk dashboard
- Pegawai primary key: ulid/uuid (bukan integer)
- Wave 1 tasks (T1 + T4) dapat berjalan paralel — tidak ada dependency satu sama lain

## [2026-03-15] Task 10: Riwayat Pendidikan CRUD

- Gunakan ULID + `SoftDeletes` untuk `riwayat_pendidikan`, mengikuti pola entity kepegawaian lain.
- Update dan delete memakai shallow route (`kepegawaian.riwayat-pendidikan.*`), sedangkan index dan store tetap nested di bawah pegawai.
- Halaman Inertia `resources/js/pages/kepegawaian/pegawai/riwayat-pendidikan.tsx` digabung sebagai sub-page tunggal untuk list + form basic agar CRUD tetap sederhana dan valid TypeScript.

## [2026-03-15] Task 13: Penghargaan

- Gunakan nested resource penuh `Route::resource('pegawai.penghargaan', PenghargaanController::class)->only(['index', 'store', 'update', 'destroy'])` agar update/delete tetap membawa konteks parent `pegawai`.
- Halaman `resources/js/pages/kepegawaian/pegawai/penghargaan.tsx` dibuat minimal valid seperti sub-page keluarga/diklat: tabel sederhana read-only untuk menjaga build stabil sambil backend CRUD tetap tersedia.

## [2026-03-15] F4 Scope Fidelity

- Verdict audit ditetapkan **REJECT** karena ada 2 deviasi scope (T1 enum creep, T15 route path mismatch), meskipun guardrails forbidden features secara umum bersih.
- Shallow route pada T9/T10 tetap dianggap acceptable sesuai known design decision plan, sehingga tidak dihitung sebagai deviasi pada F4 ini.
