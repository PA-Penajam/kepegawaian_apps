# Requirements Document

## Introduction

Dokumen ini mendefinisikan persyaratan untuk migrasi sistem Identity and Access Management (IAM) custom yang ada di aplikasi kepegawaian ke Keycloak sebagai platform IAM standar. Migrasi ini bertujuan untuk menggantikan SSO provider custom, manajemen role/permission, keamanan API antar-aplikasi, dan manajemen token dengan fitur bawaan Keycloak yang mengikuti standar OIDC/OAuth2 — sambil mempertahankan backward compatibility selama periode transisi.

## Glossary

- **Keycloak_Server**: Instance Keycloak yang dikonfigurasi sebagai authorization server dan identity provider untuk seluruh ekosistem aplikasi kepegawaian
- **Kepegawaian_App**: Aplikasi Laravel utama (kepegawaian) yang saat ini berfungsi sebagai IAM hub dan akan bermigrasi ke Keycloak
- **Keycloak_Client**: Representasi sebuah aplikasi dalam Keycloak realm, menggantikan entitas `iam_applications`
- **Keycloak_Realm**: Isolated tenant dalam Keycloak yang menampung seluruh user, client, role, dan permission untuk ekosistem kepegawaian
- **Pegawai**: Model Eloquent yang merepresentasikan data kepegawaian dan berfungsi sebagai authenticatable user; tetap menjadi source of truth untuk data HR
- **Identity_Sync_Service**: Service yang bertanggung jawab mensinkronisasi data identitas Pegawai ke Keycloak user tanpa menggantikan Pegawai sebagai master data
- **Permission_Slug**: Identifier permission berbasis slug bertitik (contoh: `cuti.pengajuan.verify`, `kenaikan-pangkat.usulan.verifikasi-kasubbag`) yang digunakan untuk fine-grained authorization
- **OIDC_Flow**: OpenID Connect Authorization Code Flow dengan PKCE yang menggantikan custom SSO code exchange
- **Service_Account**: Keycloak client credentials grant yang menggantikan HMAC signature untuk komunikasi machine-to-machine antar aplikasi
- **Token_Introspection**: Proses validasi access token Keycloak baik secara lokal (JWT signature verification) maupun remote (introspection endpoint)
- **Migration_Mode**: Konfigurasi runtime yang menentukan apakah sistem menggunakan IAM lama, Keycloak, atau keduanya secara paralel (dual-mode)
- **Dual_Mode**: Kondisi dimana sistem menerima autentikasi dari IAM lama (Sanctum) dan Keycloak (JWT) secara bersamaan selama periode transisi
- **External_App**: Aplikasi dalam ekosistem (contoh: `attendance-qr-system`) yang terhubung ke IAM hub melalui API

## Requirements

### Requirement 1: Keycloak Server Provisioning dan Realm Configuration

**User Story:** Sebagai administrator sistem, saya ingin Keycloak ter-deploy dan terkonfigurasi dengan realm yang sesuai, sehingga seluruh aplikasi dalam ekosistem dapat menggunakan Keycloak sebagai identity provider terpusat.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menyediakan konfigurasi environment variables untuk koneksi ke Keycloak_Server meliputi base URL, realm name, client ID, dan client secret
2. WHEN Kepegawaian_App melakukan startup, THE Kepegawaian_App SHALL memvalidasi konektivitas ke Keycloak_Server dan melaporkan status koneksi melalui health check endpoint
3. IF Keycloak_Server temporarily unavailable during startup, THEN THE Kepegawaian_App SHALL melanjutkan startup dan melakukan retry connectivity validation di background tanpa memblokir ketersediaan aplikasi
4. THE Kepegawaian_App SHALL menyimpan seluruh konfigurasi Keycloak dalam file `config/keycloak.php` menggunakan environment variables

### Requirement 2: Identity Synchronization (Pegawai ke Keycloak)

**User Story:** Sebagai administrator sistem, saya ingin data identitas Pegawai tersinkronisasi ke Keycloak, sehingga Keycloak dapat melakukan autentikasi tanpa menggantikan Pegawai sebagai master data HR.

#### Acceptance Criteria

1. THE Identity_Sync_Service SHALL mensinkronisasi atribut identitas Pegawai (email, NIP, nama_lengkap) ke Keycloak user representation
2. WHEN sebuah record Pegawai dibuat di database lokal, THE Identity_Sync_Service SHALL membuat user yang berkorespondensi di Keycloak_Realm
3. WHEN atribut identitas Pegawai (email, nama_lengkap, NIP) diperbarui di database lokal, THE Identity_Sync_Service SHALL memperbarui atribut yang berkorespondensi pada Keycloak user
4. WHEN sebuah record Pegawai di-soft-delete, THE Identity_Sync_Service SHALL menonaktifkan (disable) user yang berkorespondensi di Keycloak tanpa menghapusnya
5. WHILE memproses soft-delete event untuk sebuah record Pegawai, THE Identity_Sync_Service SHALL mencegah pembuatan atau modifikasi Keycloak user untuk record tersebut hingga operasi disable selesai
6. THE Identity_Sync_Service SHALL menggunakan NIP sebagai identifier unik untuk mapping antara Pegawai lokal dan Keycloak user
7. IF sinkronisasi ke Keycloak_Server gagal, THEN THE Identity_Sync_Service SHALL mencatat error ke activity log dan menambahkan job ke retry queue tanpa menggagalkan operasi pada Pegawai lokal
8. THE Kepegawaian_App SHALL menyediakan Artisan command untuk melakukan bulk sync seluruh Pegawai yang ada ke Keycloak_Realm

### Requirement 3: Authentication via Keycloak OIDC

**User Story:** Sebagai pengguna aplikasi kepegawaian, saya ingin login melalui Keycloak menggunakan standar OIDC, sehingga saya mendapatkan pengalaman Single Sign-On yang aman dan standar.

#### Acceptance Criteria

1. WHEN pengguna mengakses halaman yang memerlukan autentikasi dan belum ter-autentikasi, THE Kepegawaian_App SHALL mengarahkan pengguna ke Keycloak login page melalui OIDC Authorization Code Flow dengan PKCE
2. WHEN Keycloak mengembalikan authorization code setelah login berhasil, THE Kepegawaian_App SHALL menukar code tersebut dengan access token, refresh token, dan ID token melalui Keycloak token endpoint
3. WHEN access token diterima dari Keycloak, THE Kepegawaian_App SHALL membuat session Laravel lokal yang terhubung dengan Pegawai berdasarkan mapping NIP dari ID token claims
4. IF NIP yang terdapat dalam ID token tidak ditemukan di tabel Pegawai, THEN THE Kepegawaian_App SHALL menolak autentikasi dan menampilkan pesan error bahwa akun belum terdaftar di sistem kepegawaian
5. WHEN pengguna melakukan logout dari Kepegawaian_App, THE Kepegawaian_App SHALL menginvalidasi session lokal dan melakukan redirect ke Keycloak end-session endpoint untuk single logout
6. WHEN ID token validation gagal (invalid signature atau expired), THE Kepegawaian_App SHALL secara eksplisit menolak seluruh claims dalam token dan menolak authentication attempt

### Requirement 4: SSO untuk External Applications via Keycloak

**User Story:** Sebagai pengguna yang mengakses aplikasi dalam ekosistem (contoh: attendance-qr-system), saya ingin bisa login sekali melalui Keycloak dan mendapatkan akses ke semua aplikasi terkait tanpa login ulang.

#### Acceptance Criteria

1. WHEN External_App mengarahkan pengguna ke Keycloak untuk autentikasi dan pengguna sudah memiliki session aktif di Keycloak, THE Keycloak_Server SHALL mengeluarkan authorization code tanpa menampilkan login page (SSO silent login)
2. THE Kepegawaian_App SHALL enforce silent login behavior dan SHALL NOT mengizinkan konfigurasi Keycloak_Client yang memaksa login prompt ketika pengguna memiliki session aktif di Keycloak
3. THE Kepegawaian_App SHALL mendaftarkan setiap External_App sebagai Keycloak_Client terpisah dalam Keycloak_Realm dengan konfigurasi redirect URI yang sesuai
4. WHEN External_App yang belum bermigrasi ke Keycloak melakukan request ke legacy SSO endpoint (`/sso/login`), THE Kepegawaian_App SHALL tetap memproses request tersebut menggunakan flow lama selama Migration_Mode aktif
5. THE Kepegawaian_App SHALL menyediakan dokumentasi migrasi untuk setiap External_App yang berisi langkah-langkah konfigurasi Keycloak_Client

### Requirement 5: Role dan Permission Mapping ke Keycloak

**User Story:** Sebagai administrator, saya ingin seluruh role dan permission yang ada di IAM lama ter-representasi di Keycloak, sehingga authorization logic tetap berfungsi tanpa perubahan di application layer.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL memetakan setiap `IamRole` yang ada ke Keycloak client role pada Keycloak_Client yang berkorespondensi
2. THE Kepegawaian_App SHALL memetakan setiap `IamPermission` slug ke Keycloak fine-grained permission atau client scope attribute
3. WHEN access token dikeluarkan oleh Keycloak_Server, THE Keycloak_Server SHALL menyertakan seluruh Permission_Slug milik pengguna dalam token claims (custom claim `permissions`)
4. THE Kepegawaian_App SHALL menyediakan Artisan command untuk melakukan migrasi seluruh role dan permission dari tabel IAM lama ke Keycloak_Realm
5. WHEN role atau permission baru ditambahkan melalui admin interface, THE Kepegawaian_App SHALL membuat representasi yang berkorespondensi di Keycloak_Server secara sinkron
6. IF Keycloak_Server temporarily unavailable ketika role atau permission ditambahkan melalui admin interface, THEN THE Kepegawaian_App SHALL mengizinkan operasi admin untuk berhasil dan mengantrikan sinkronisasi Keycloak untuk retry kemudian
7. WHEN bulk migration operations dieksekusi, THE Kepegawaian_App SHALL memproses pembuatan role dan permission secara asinkron melalui queue jobs
8. THE Kepegawaian_App SHALL mempertahankan format Permission_Slug yang sama (contoh: `cuti.pengajuan.verify`) di Keycloak claims agar kompatibel dengan middleware dan frontend yang ada

### Requirement 6: Token Validation dan Authorization Middleware

**User Story:** Sebagai developer, saya ingin middleware authorization tetap berfungsi dengan Keycloak tokens, sehingga seluruh endpoint yang dilindungi tetap aman tanpa perubahan di route definition.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menyediakan middleware pengganti `VerifyIamPermission` yang dapat memvalidasi permission dari Keycloak JWT access token claims
2. WHILE Migration_Mode dalam kondisi dual-mode, THE Kepegawaian_App SHALL menerima autentikasi dari Sanctum token (legacy) maupun Keycloak JWT access token
3. WHEN Migration_Mode bernilai `keycloak`, THE Kepegawaian_App SHALL menolak seluruh Sanctum tokens dan hanya menerima Keycloak JWT tokens
4. WHEN request API membawa Bearer token, THE Kepegawaian_App SHALL mendeteksi tipe token (Sanctum atau Keycloak JWT) dan memvalidasi sesuai mekanisme yang tepat
5. THE Kepegawaian_App SHALL memvalidasi Keycloak JWT secara lokal menggunakan cached realm public key tanpa melakukan network call ke Keycloak pada setiap request
6. WHEN Keycloak realm public key di-rotate, THE Kepegawaian_App SHALL memperbarui cached key dalam waktu maksimal 5 menit setelah rotasi
7. THE Kepegawaian_App SHALL mengimplementasikan periodic key refresh (setiap 4 jam) sebagai fallback mechanism, dan SHALL melakukan re-fetch key dari Keycloak ketika JWT validation gagal karena unknown key ID
8. THE Kepegawaian_App SHALL mempertahankan alias middleware yang sama (`permission`, `iam.permission`) agar route definition tidak perlu berubah

### Requirement 7: Inter-Application API Security (Machine-to-Machine)

**User Story:** Sebagai developer External_App, saya ingin menggunakan standar OAuth2 Client Credentials untuk komunikasi antar aplikasi, sehingga tidak perlu lagi mengimplementasikan HMAC signature secara manual.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menerima access token dari Keycloak Client Credentials Grant sebagai pengganti HMAC signature untuk autentikasi machine-to-machine
2. WHILE Migration_Mode dalam kondisi dual-mode, THE Kepegawaian_App SHALL menerima request yang menggunakan HMAC signature (legacy VerifyIamSignature) maupun Keycloak service account token
3. WHEN Migration_Mode bernilai `keycloak`, THE Kepegawaian_App SHALL menolak seluruh request HMAC signature dan hanya menerima Keycloak service account tokens
4. WHEN External_App mengirim request dengan Keycloak service account token, THE Kepegawaian_App SHALL memvalidasi token dan mengidentifikasi aplikasi pemanggil dari client_id claim
5. THE Kepegawaian_App SHALL memetakan setiap `IamApplication` yang ada ke Keycloak_Client dengan Service_Account enabled
6. WHEN External_App menggunakan Keycloak service account token, THE Kepegawaian_App SHALL menerapkan scope/permission yang sama seperti yang sebelumnya dikonfigurasi pada `IamApplication`

### Requirement 8: Frontend Permission Integration

**User Story:** Sebagai developer frontend, saya ingin permissions tetap tersedia di Inertia shared props dengan format yang sama, sehingga conditional rendering di React components tidak perlu diubah.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL tetap membagikan array permissions ke frontend melalui Inertia shared props dengan format yang identik (array of Permission_Slug strings)
2. WHEN autentikasi menggunakan Keycloak, THE Kepegawaian_App SHALL mengekstrak permissions dari Keycloak access token claims untuk dibagikan ke frontend
3. WHEN autentikasi menggunakan legacy Sanctum (selama dual-mode), THE Kepegawaian_App SHALL mengekstrak permissions dari database IAM lama seperti sebelumnya
4. THE Kepegawaian_App SHALL tetap membagikan informasi roles pengguna melalui Inertia shared props dengan format yang identik (array of role name strings)

### Requirement 9: Migration Mode dan Phased Rollout

**User Story:** Sebagai administrator sistem, saya ingin bisa mengontrol fase migrasi secara bertahap menggunakan konfigurasi, sehingga migrasi dapat dilakukan tanpa downtime dan dengan kemampuan rollback.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menyediakan konfigurasi Migration_Mode dengan tiga nilai: `legacy` (hanya IAM lama), `dual` (keduanya aktif), dan `keycloak` (hanya Keycloak)
2. WHEN Migration_Mode bernilai `legacy`, THE Kepegawaian_App SHALL menggunakan seluruh mekanisme IAM lama tanpa perubahan perilaku
3. WHEN Migration_Mode bernilai `dual`, THE Kepegawaian_App SHALL menerima autentikasi dari kedua sistem dan menggunakan Keycloak sebagai primary dengan fallback ke legacy
4. WHEN Migration_Mode bernilai `keycloak`, THE Kepegawaian_App SHALL hanya menggunakan Keycloak untuk seluruh autentikasi dan authorization
5. THE Kepegawaian_App SHALL memungkinkan perubahan Migration_Mode melalui environment variable tanpa perlu deployment ulang (config reload)
6. IF terjadi error pada Keycloak_Server saat Migration_Mode bernilai `dual` dan fallback ke legacy diaktifkan, THEN THE Kepegawaian_App SHALL mencatat warning ke log; logging hanya dilakukan ketika error Keycloak benar-benar terjadi dan memicu fallback

### Requirement 10: User Session dan Token Lifecycle Management

**User Story:** Sebagai pengguna, saya ingin session saya dikelola dengan baik termasuk auto-refresh dan timeout yang konsisten, sehingga pengalaman penggunaan aplikasi tetap lancar.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menyimpan Keycloak refresh token secara terenkripsi di server-side session
2. WHEN Keycloak access token mendekati expiry (kurang dari 60 detik), THE Kepegawaian_App SHALL menggunakan refresh token untuk memperoleh access token baru secara transparan tanpa mengganggu pengguna
3. WHEN access token expired tetapi refresh token masih valid, THE Kepegawaian_App SHALL melakukan automatic refresh terlebih dahulu; hanya redirect ke login jika refresh token juga expired atau invalid
4. IF refresh token sudah expired atau invalid, THEN THE Kepegawaian_App SHALL mengarahkan pengguna ke halaman login Keycloak
5. THE Kepegawaian_App SHALL mengkonfigurasi TTL access token di Keycloak_Client sesuai kebijakan keamanan yang ada (default 8 jam, mengikuti konfigurasi `iam.token_ttl_hours` yang sudah ada)
6. WHEN administrator menonaktifkan user di Keycloak, THE Kepegawaian_App SHALL menolak request dari user tersebut pada validasi token berikutnya

### Requirement 11: Audit Trail dan Activity Logging

**User Story:** Sebagai administrator keamanan, saya ingin seluruh aktivitas autentikasi dan perubahan authorization tercatat di audit log, sehingga dapat dilakukan audit trail yang lengkap.

#### Acceptance Criteria

1. WHEN pengguna berhasil login melalui Keycloak, THE Kepegawaian_App SHALL mencatat event login ke Spatie activity log dengan detail user, timestamp, dan IP address
2. WHEN pengguna gagal login (NIP tidak ditemukan atau token invalid), THE Kepegawaian_App SHALL mencatat event failed login ke activity log
3. WHEN role atau permission pengguna berubah di Keycloak, THE Kepegawaian_App SHALL mencatat perubahan tersebut ke activity log
4. THE Kepegawaian_App SHALL NOT mencatat perubahan role/permission ke activity log jika perubahan terjadi dalam konteks login attempt yang gagal
5. WHEN Migration_Mode berubah, THE Kepegawaian_App SHALL mencatat perubahan mode ke activity log beserta informasi siapa yang mengubah

### Requirement 12: Data Migration dan Rollback

**User Story:** Sebagai administrator sistem, saya ingin memiliki mekanisme migrasi data yang aman dari IAM lama ke Keycloak dengan kemampuan rollback, sehingga migrasi tidak menyebabkan kehilangan data.

#### Acceptance Criteria

1. THE Kepegawaian_App SHALL menyediakan Artisan command untuk mengekspor seluruh data IAM (applications, roles, permissions, user-role assignments) ke format yang dapat diimpor ke Keycloak
2. THE Kepegawaian_App SHALL mempertahankan tabel IAM lama (tidak menghapus) selama Migration_Mode belum bernilai `keycloak` secara permanen
3. WHEN migrasi ke Keycloak selesai, THE Kepegawaian_App SHALL menyediakan Artisan command untuk membersihkan tabel IAM lama yang tersedia segera setelah migrasi selesai tanpa perlu menunggu stability period
4. THE Kepegawaian_App SHALL menyediakan Artisan command untuk memvalidasi konsistensi data antara tabel IAM lama dan Keycloak (perbedaan role/permission/assignment)
5. IF ditemukan inkonsistensi data saat validasi, THEN THE Kepegawaian_App SHALL menghasilkan laporan detail yang mencantumkan entitas mana yang berbeda dan rekomendasi resolusi
6. IF proses report generation gagal setelah validasi konsistensi berhasil, THEN THE Kepegawaian_App SHALL memperlakukan seluruh operasi validasi sebagai gagal dan mengembalikan exit code non-zero

### Requirement 13: Two-Factor Authentication via Keycloak

**User Story:** Sebagai pengguna dengan 2FA aktif, saya ingin konfigurasi 2FA saya tetap berfungsi setelah migrasi ke Keycloak, sehingga keamanan akun saya terjaga.

#### Acceptance Criteria

1. WHEN pengguna memiliki two-factor authentication aktif di Fortify (two_factor_confirmed_at tidak null), THE Identity_Sync_Service SHALL mengkonfigurasi required action OTP di Keycloak user yang berkorespondensi
2. THE Kepegawaian_App SHALL mendelegasikan seluruh flow 2FA (enrollment, challenge, recovery) ke Keycloak setelah Migration_Mode bernilai `keycloak`
3. WHILE Migration_Mode bernilai `dual`, THE Kepegawaian_App SHALL menggunakan 2FA Fortify untuk login web langsung dan membiarkan Keycloak menangani 2FA untuk OIDC flow
4. WHILE Migration_Mode bernilai `dual`, IF pengguna memiliki 2FA aktif, THEN THE Kepegawaian_App SHALL memastikan minimal satu mekanisme 2FA (Fortify atau Keycloak OTP) diterapkan sebelum mengizinkan akses
