# Employee Management API

<cite>
**Referenced Files in This Document**
- [routes/api.php](file://routes/api.php)
- [app/Http/Controllers/Api/PegawaiApiController.php](file://app/Http/Controllers/Api/PegawaiApiController.php)
- [app/Http/Resources/PegawaiApiResource.php](file://app/Http/Resources/PegawaiApiResource.php)
- [app/Http/Middleware/VerifyHmacSignature.php](file://app/Http/Middleware/VerifyHmacSignature.php)
- [app/Models/Pegawai.php](file://app/Models/Pegawai.php)
- [app/Models/RefJabatan.php](file://app/Models/RefJabatan.php)
- [app/Models/RefUnitKerja.php](file://app/Models/RefUnitKerja.php)
- [app/Models/RefPangkat.php](file://app/Models/RefPangkat.php)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php](file://app/Http/Controllers/Kepegawaian/PegawaiController.php)
- [app/Http/Controllers/Kepegawaian/KeluargaController.php](file://app/Http/Controllers/Kepegawaian/KeluargaController.php)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php)
- [app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php](file://app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php)
- [app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php](file://app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php)
- [app/Models/PengajuanPerubahanData.php](file://app/Models/PengajuanPerubahanData.php)
- [app/Enums/StatusPengajuanPerubahanData.php](file://app/Enums/StatusPengajuanPerubahanData.php)
- [config/kepegawaian.php](file://config/kepegawaian.php)
- [config/sanctum.php](file://config/sanctum.php)
- [tests/Feature/Api/PegawaiApiTest.php](file://tests/Feature/Api/PegawaiApiTest.php)
- [tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php](file://tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php)
</cite>

## Update Summary
**Changes Made**
- Added new approval workflow endpoints for self-service data change requests
- Documented operator interception mechanisms that convert direct writes to pending approval requests
- Added approval inbox functionality for validators with approve/reject operations
- Updated controller implementations to show operator interception patterns
- Added comprehensive approval workflow documentation with domain-specific routing

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Approval Workflow System](#approval-workflow-system)
7. [Dependency Analysis](#dependency-analysis)
8. [Performance Considerations](#performance-considerations)
9. [Troubleshooting Guide](#troubleshooting-guide)
10. [Conclusion](#conclusion)
11. [Appendices](#appendices)

## Introduction
This document describes the Employee Management API endpoints designed for integration with external systems such as attendance QR systems. It covers:
- RESTful endpoints for single NIP lookup and batch/search retrieval
- Authentication and security layers using Laravel Sanctum tokens and HMAC signature verification
- Request and response schemas for employee data
- Validation rules for NIP format, batch sizes, and search parameters
- Error handling scenarios and practical integration examples
- **New**: Approval workflow system for self-service data changes with operator interception mechanisms
- **New**: Validator approval inbox for managing pending data change requests

## Project Structure
The API is implemented under the API routing group with layered middleware for security and rate limiting. The controller orchestrates data retrieval and transformation, while the resource handles standardized JSON output. Supporting models define relationships to position, unit, and rank data. **New approval workflow endpoints** are integrated into the kepegawaian routing group with dedicated middleware for validator access.

```mermaid
graph TB
Client["External System<br/>Attendance QR System"] --> Routes["Routes<br/>/api/v1/pegawai"]
Routes --> MW1["Middleware<br/>auth:sanctum"]
Routes --> MW2["Middleware<br/>verify.hmac"]
Routes --> MW3["Middleware<br/>throttle:60,1"]
MW1 --> Ctrl["Controller<br/>PegawaiApiController"]
Ctrl --> Model["Model<br/>Pegawai"]
Model --> Jabatan["RefJabatan"]
Model --> Unit["RefUnitKerja"]
Model --> Pangkat["RefPangkat"]
Ctrl --> Resource["Resource<br/>PegawaiApiResource"]
Resource --> Client
ApprovalRoutes["Approval Routes<br/>/kepegawaian/pengajuan"] --> ApprovalMW["Middleware<br/>iam.permission:pengajuan-perubahan.validate"]
ApprovalMW --> ApprovalCtrl["Controller<br/>ApprovalPengajuanPerubahanDataController"]
ApprovalCtrl --> ApprovalModel["Model<br/>PengajuanPerubahanData"]
```

**Diagram sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)
- [app/Http/Controllers/Api/PegawaiApiController.php:20-112](file://app/Http/Controllers/Api/PegawaiApiController.php#L20-L112)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:18-69](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L18-L69)

**Section sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)

## Core Components
- Routes: Define the base path, middleware stack, and endpoint patterns for single and batch/search retrieval.
- Controller: Implements business logic for single lookup, batch retrieval, and search with prioritization rules.
- Resource: Transforms model data into a normalized JSON structure suitable for external consumers.
- Middleware: Enforces Sanctum authentication and HMAC signature verification with timestamp validation.
- Models: Define relationships to position, unit, and rank data, and scopes for filtering.
- **New**: Approval workflow controllers for managing data change requests with validator approval.
- **New**: Service layer for handling operator interception and approval processing.

**Section sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [app/Http/Controllers/Api/PegawaiApiController.php:20-112](file://app/Http/Controllers/Api/PegawaiApiController.php#L20-L112)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:18-69](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L18-L69)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:13-134](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L13-L134)

## Architecture Overview
The API enforces four layers of security:
1. Transport security via HTTPS
2. Authentication via Laravel Sanctum tokens
3. Request integrity via HMAC-SHA256 signatures
4. DDoS protection via per-endpoint rate limiting

**New**: Approval workflow adds additional security layers:
- Role-based access control for validator permissions
- Duplicate pending request prevention
- Atomic approval/rejection operations
- Audit trail with before/after snapshots

```mermaid
sequenceDiagram
participant Ext as "External System"
participant API as "API Routes"
participant Auth as "Sanctum Middleware"
participant HMAC as "HMAC Middleware"
participant Ctrl as "PegawaiApiController"
participant DB as "Eloquent ORM"
Ext->>API : "GET /api/v1/pegawai/{nip}" with headers
API->>Auth : "Authenticate request"
Auth-->>API : "Authenticated user"
API->>HMAC : "Verify signature and timestamp"
HMAC-->>API : "Signature valid"
API->>Ctrl : "Dispatch to controller"
Ctrl->>DB : "Load Pegawai with relations"
DB-->>Ctrl : "Pegawai data"
Ctrl-->>Ext : "JSON response"
Note over Ext,Ctrl : New Approval Workflow
participant ApprovalAPI as "Approval Routes"
participant ApprovalAuth as "Validator Middleware"
participant ApprovalCtrl as "Approval Controller"
Ext->>ApprovalAPI : "POST /kepegawaian/pengajuan/{id}/approve"
ApprovalAPI->>ApprovalAuth : "Check validator permission"
ApprovalAuth-->>ApprovalAPI : "Permission granted"
ApprovalAPI->>ApprovalCtrl : "Process approval"
ApprovalCtrl->>DB : "Atomic write to data tables"
DB-->>ApprovalCtrl : "Transaction committed"
ApprovalCtrl-->>Ext : "Success response"
```

**Diagram sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)
- [app/Http/Middleware/VerifyHmacSignature.php:25-62](file://app/Http/Middleware/VerifyHmacSignature.php#L25-L62)
- [app/Http/Controllers/Api/PegawaiApiController.php:27-41](file://app/Http/Controllers/Api/PegawaiApiController.php#L27-L41)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:53-67](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L53-L67)

## Detailed Component Analysis

### Endpoint: GET /api/v1/pegawai/{nip}
- Purpose: Retrieve a single employee by 18-digit NIP.
- Path parameter: {nip} must match exactly 18 digits.
- Authentication: Requires a valid Sanctum token.
- Signature: Must pass HMAC verification with X-Timestamp and X-Signature headers.
- Response: JSON object containing a top-level data field with transformed employee data.
- Error handling:
  - 404 Not Found when NIP does not exist.
  - 401 Unauthorized for missing or invalid credentials.
  - 422 Unprocessable Entity for invalid NIP format.

```mermaid
sequenceDiagram
participant Ext as "External System"
participant API as "Route"
participant HMAC as "HMAC Middleware"
participant Ctrl as "show()"
participant DB as "Eloquent"
Ext->>API : "GET /api/v1/pegawai/{nip}"
API->>HMAC : "Verify signature"
HMAC-->>API : "OK"
API->>Ctrl : "Call show(nip)"
Ctrl->>DB : "Find by nip"
DB-->>Ctrl : "Pegawai or null"
alt "Found"
Ctrl-->>Ext : "200 OK with {data}"
else "Not Found"
Ctrl-->>Ext : "404 with {message, errors}"
end
```

**Diagram sources**
- [routes/api.php:26-27](file://routes/api.php#L26-L27)
- [app/Http/Middleware/VerifyHmacSignature.php:25-62](file://app/Http/Middleware/VerifyHmacSignature.php#L25-L62)
- [app/Http/Controllers/Api/PegawaiApiController.php:27-41](file://app/Http/Controllers/Api/PegawaiApiController.php#L27-L41)

**Section sources**
- [routes/api.php:26-27](file://routes/api.php#L26-L27)
- [app/Http/Controllers/Api/PegawaiApiController.php:27-41](file://app/Http/Controllers/Api/PegawaiApiController.php#L27-L41)
- [tests/Feature/Api/PegawaiApiTest.php:108-117](file://tests/Feature/Api/PegawaiApiTest.php#L108-L117)

### Endpoint: GET /api/v1/pegawai
- Purpose: Batch lookup by NIP array or search by name with optional status filter.
- Modes:
  - Batch lookup: nip[] parameter (array of up to 50 NIPs, each exactly 18 digits)
  - Search: search (name pattern) and status (default: aktif)
- Priority: If nip[] is present, batch mode takes precedence over search.
- Authentication: Requires a valid Sanctum token.
- Signature: Must pass HMAC verification with X-Timestamp and X-Signature headers.
- Response:
  - Batch mode: { data: [...], not_found: [...] }
  - Search mode: { data: [...], meta: { total, per_page } }
- Error handling:
  - 401 Unauthorized for missing or invalid credentials.
  - 422 Unprocessable Entity for invalid NIP format, exceeding batch limit, or malformed queries.

```mermaid
flowchart TD
Start(["Request Received"]) --> CheckNip["Has 'nip[]' parameter?"]
CheckNip --> |Yes| ValidateBatch["Validate array length <= 50<br/>and each NIP is 18 digits"]
ValidateBatch --> |Invalid| Return422["Return 422"]
ValidateBatch --> |Valid| LoadBatch["Load employees by nip IN (?)"]
LoadBatch --> BuildResponseBatch["Build {data, not_found}"]
CheckNip --> |No| ApplySearch["Apply filters:<br/>status=aktif (optional)<br/>search=name LIKE %term%"]
ApplySearch --> Limit["Limit to 20 results"]
Limit --> CountTotal["Count total matches"]
CountTotal --> BuildResponseSearch["Build {data, meta.total, meta.per_page}"]
BuildResponseBatch --> End(["Return 200"])
BuildResponseSearch --> End
Return422 --> End
```

**Diagram sources**
- [app/Http/Controllers/Api/PegawaiApiController.php:52-110](file://app/Http/Controllers/Api/PegawaiApiController.php#L52-L110)
- [tests/Feature/Api/PegawaiApiTest.php:119-172](file://tests/Feature/Api/PegawaiApiTest.php#L119-L172)

**Section sources**
- [app/Http/Controllers/Api/PegawaiApiController.php:52-110](file://app/Http/Controllers/Api/PegawaiApiController.php#L52-L110)
- [tests/Feature/Api/PegawaiApiTest.php:119-172](file://tests/Feature/Api/PegawaiApiTest.php#L119-L172)

### Authentication and Security
- Sanctum Authentication:
  - Requires a valid Sanctum token in the Authorization header.
  - Configuration supports stateful domains and token prefix settings.
- HMAC Signature Verification:
  - Validates X-Timestamp and X-Signature headers.
  - Rejects requests older than 5 minutes (anti-replay).
  - Computes HMAC-SHA256 over METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP using a shared secret.
  - Secret key is configured via ATTENDANCE_HMAC_SECRET in environment.
- Rate Limiting:
  - Throttle:60,1 applied to pegawai endpoints to prevent abuse.

```mermaid
sequenceDiagram
participant Ext as "External System"
participant HMAC as "VerifyHmacSignature"
participant Sanctum as "Sanctum Guard"
participant Ctrl as "Controller"
Ext->>HMAC : "Headers : X-Timestamp, X-Signature"
HMAC->>HMAC : "Validate timestamp window"
HMAC->>HMAC : "Reconstruct payload and compare HMAC"
HMAC-->>Ext : "401 if invalid"
HMAC->>Sanctum : "Authenticate request"
Sanctum-->>Ext : "401 if invalid"
Sanctum->>Ctrl : "Proceed to controller"
```

**Diagram sources**
- [app/Http/Middleware/VerifyHmacSignature.php:25-62](file://app/Http/Middleware/VerifyHmacSignature.php#L25-L62)
- [config/sanctum.php:40-85](file://config/sanctum.php#L40-L85)
- [config/kepegawaian.php:15](file://config/kepegawaian.php#L15)

**Section sources**
- [app/Http/Middleware/VerifyHmacSignature.php:25-62](file://app/Http/Middleware/VerifyHmacSignature.php#L25-L62)
- [config/kepegawaian.php:15](file://config/kepegawaian.php#L15)
- [config/sanctum.php:40-85](file://config/sanctum.php#L40-L85)
- [routes/api.php:22](file://routes/api.php#L22)

### Response Schema: Employee Data
The API transforms internal model data into a standardized JSON structure for external consumption. The resource maps fields and enriches with derived values.

```mermaid
classDiagram
class PegawaiApiResource {
+toArray(request) array
-resolveTingkatPerjalanan() string?
}
class Pegawai {
+jabatan
+unitKerja
+pangkat
}
class RefJabatan {
+nama
}
class RefUnitKerja {
+nama
}
class RefPangkat {
+nama
+kode
+golongan
+ruang
}
PegawaiApiResource --> Pegawai : "transforms"
Pegawai --> RefJabatan : "belongsTo"
Pegawai --> RefUnitKerja : "belongsTo"
Pegawai --> RefPangkat : "belongsTo"
```

**Diagram sources**
- [app/Http/Resources/PegawaiApiResource.php:19-61](file://app/Http/Resources/PegawaiApiResource.php#L19-L61)
- [app/Models/Pegawai.php:67-82](file://app/Models/Pegawai.php#L67-L82)
- [app/Models/RefJabatan.php:18-24](file://app/Models/RefJabatan.php#L18-L24)
- [app/Models/RefUnitKerja.php:19-24](file://app/Models/RefUnitKerja.php#L19-L24)
- [app/Models/RefPangkat.php:17-24](file://app/Models/RefPangkat.php#L17-L24)

Field mapping summary:
- nip → nip
- nama_lengkap → nama
- jabatan → jabatan (from relation)
- unit_kerja → unit_kerja (from relation)
- status_pegawai → status_pegawai (enum value)
- foto → foto_url (asset URL if present)
- pangkat_nama → pangkat->nama
- pangkat_kode → pangkat->kode
- pangkat_golongan → formatted string "nama / golongan/ruang"
- tingkat_perjalanan → derived from golongan/ruang
- no_telepon → no_telepon
- email → email

**Section sources**
- [app/Http/Resources/PegawaiApiResource.php:26-44](file://app/Http/Resources/PegawaiApiResource.php#L26-L44)
- [app/Models/Pegawai.php:67-82](file://app/Models/Pegawai.php#L67-L82)

### Validation Rules
- Single lookup NIP:
  - Must be exactly 18 digits.
- Batch lookup nip[]:
  - Array with maximum 50 items.
  - Each item must be exactly 18 digits.
- Search:
  - Optional status defaults to aktif.
  - search parameter uses LIKE %term% against nama_lengkap.
  - Results limited to 20 entries; total count returned separately.

**Section sources**
- [routes/api.php:27](file://routes/api.php#L27)
- [app/Http/Controllers/Api/PegawaiApiController.php:69-72](file://app/Http/Controllers/Api/PegawaiApiController.php#L69-L72)
- [app/Http/Controllers/Api/PegawaiApiController.php:94-99](file://app/Http/Controllers/Api/PegawaiApiController.php#L94-L99)
- [tests/Feature/Api/PegawaiApiTest.php:133-143](file://tests/Feature/Api/PegawaiApiTest.php#L133-L143)
- [tests/Feature/Api/PegawaiApiTest.php:178-215](file://tests/Feature/Api/PegawaiApiTest.php#L178-L215)

### Error Handling
Common HTTP responses:
- 401 Unauthorized:
  - Missing or invalid Sanctum token.
  - Missing or invalid HMAC headers.
  - Tampered query string after signing.
  - Expired timestamp (> 5 minutes).
- 404 Not Found:
  - Single lookup NIP not found.
- 422 Unprocessable Entity:
  - Invalid NIP format (not 18 digits).
  - Too many NIPs in batch (> 50).
  - Malformed search parameters.

**Section sources**
- [app/Http/Middleware/VerifyHmacSignature.php:31-43](file://app/Http/Middleware/VerifyHmacSignature.php#L31-L43)
- [app/Http/Controllers/Api/PegawaiApiController.php:33-38](file://app/Http/Controllers/Api/PegawaiApiController.php#L33-L38)
- [tests/Feature/Api/PegawaiApiTest.php:37-81](file://tests/Feature/Api/PegawaiApiTest.php#L37-L81)
- [tests/Feature/Api/PegawaiApiTest.php:108-117](file://tests/Feature/Api/PegawaiApiTest.php#L108-L117)
- [tests/Feature/Api/PegawaiApiTest.php:133-143](file://tests/Feature/Api/PegawaiApiTest.php#L133-L143)
- [tests/Feature/Api/PegawaiApiTest.php:178-215](file://tests/Feature/Api/PegawaiApiTest.php#L178-L215)

### Practical Examples and Integration Patterns
- Single lookup:
  - Method: GET
  - Path: /api/v1/pegawai/{nip}
  - Headers: X-Timestamp, X-Signature, Accept: application/json
  - Body: none
  - Response: { data: { nip, nama, jabatan, unit_kerja, status_pegawai, foto_url, pangkat_nama, pangkat_kode, pangkat_golongan, tingkat_perjalanan, no_telepon, email } }
- Batch lookup:
  - Method: GET
  - Path: /api/v1/pegawai?nip[]=...
  - Query: nip[] (array of up to 50 NIPs, each 18 digits)
  - Response: { data: [...], not_found: [...] }
- Search:
  - Method: GET
  - Path: /api/v1/pegawai?search=...&status=aktif
  - Query: search (string), status (optional, default aktif)
  - Response: { data: [...], meta: { total, per_page: 20 } }

Integration tips:
- Always sign requests with HMAC-SHA256 using the shared secret and include X-Timestamp and X-Signature.
- Use Sanctum to obtain a valid token for authentication.
- Respect rate limits (60 requests per minute).
- Handle not_found in batch mode to reconcile absent NIPs.

**Section sources**
- [routes/api.php:26-30](file://routes/api.php#L26-L30)
- [app/Http/Controllers/Api/PegawaiApiController.php:52-110](file://app/Http/Controllers/Api/PegawaiApiController.php#L52-L110)
- [tests/Feature/Api/PegawaiApiTest.php:119-172](file://tests/Feature/Api/PegawaiApiTest.php#L119-L172)

## Approval Workflow System

### Overview
The approval workflow system enables self-service data changes through a structured approval process. Operators can propose changes that are automatically converted to pending requests instead of direct writes, ensuring proper authorization and audit trails.

### Operator Interception Mechanisms
**Updated** The system implements automatic interception of operator actions to ensure all data changes go through the approval process.

#### Profil Pribadi Updates (PegawaiController)
- **Interception Point**: Update operations in `update()` method
- **Behavior**: When operator submits changes, they're converted to pending requests
- **Domain**: `profil_pribadi` with supported fields: nama_lengkap, tempat_lahir, tanggal_lahir, status_perkawinan, alamat, no_telepon, email
- **Storage**: Uses `SubmitPengajuanPerubahanDataService` to create pending requests

#### Keluarga Operations (KeluargaController)
- **Interception Point**: Store, update, and destroy operations
- **Behavior**: All keluarga modifications become pending requests
- **Domain Classification**:
  - Pasangan: Suami/Istri → `pasangan`
  - Anak → `anak`
  - Orang Tua: Ayah/IbuKandung/IbuTiri/AyahMertua/IbuMertua → `orang_tua`
  - Lainnya → `keluarga_lain`
- **Atomicity**: Maintains data integrity through transaction boundaries

```mermaid
sequenceDiagram
participant Operator as "Operator"
participant Controller as "Pegawai/Keluarga Controller"
participant Service as "SubmitPengajuanPerubahanDataService"
participant DB as "Database"
participant Validator as "Validator"
Operator->>Controller : "POST/PUT/PATCH request"
Controller->>Controller : "Check if user is operator"
alt "Is Operator"
Controller->>Service : "submitPengajuanPerubahanData.handle()"
Service->>DB : "Create pending request (scope_key locked)"
DB-->>Service : "Pending request created"
Service-->>Controller : "Return pending request"
Controller-->>Operator : "Success with pending status"
else "Is Pegawai"
Controller->>DB : "Direct write to data tables"
DB-->>Controller : "Data updated"
Controller-->>Operator : "Success with immediate effect"
end
Note over Validator,DB : "Validator Inbox"
Validator->>DB : "View pending requests"
Validator->>DB : "Approve or Reject"
DB-->>Validator : "Atomic write to data tables"
```

**Diagram sources**
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:213-234](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L213-L234)
- [app/Http/Controllers/Kepegawaian/KeluargaController.php:65-91](file://app/Http/Controllers/Kepegawaian/KeluargaController.php#L65-L91)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:22-56](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L22-L56)

### Approval Inbox for Validators
**New** Dedicated approval workflow accessible only to validators with proper permissions.

#### Routes and Access Control
- **Route**: `/kepegawaian/pengajuan`
- **Permission**: `iam.permission:pengajuan-perubahan.validate`
- **Endpoints**:
  - GET `/kepegawaian/pengajuan` - List pending requests
  - GET `/kepegawaian/pengajuan/{pengajuan}` - View request details with diff
  - POST `/kepegawaian/pengajuan/{pengajuan}/approve` - Approve request
  - POST `/kepegawaian/pengajuan/{pengajuan}/reject` - Reject request

#### Request Processing Logic
- **Duplicate Prevention**: Uses `scope_key` to prevent concurrent duplicate pending requests
- **Locking**: Transaction-level locking prevents race conditions
- **Audit Trail**: Complete before/after snapshots stored for review
- **Domain Support**: 
  - `profil_pribadi`: Direct pegawai table updates
  - `pasangan/anak/orang_tua/keluarga_lain`: Keluarga table mutations

```mermaid
flowchart TD
Start(["Operator Action"]) --> CheckUser["Check if user is operator"]
CheckUser --> |Yes| Intercept["Convert to Pending Request"]
CheckUser --> |No| DirectWrite["Direct Write to Data Tables"]
Intercept --> ScopeKey["Generate scope_key"]
ScopeKey --> Lock["Transaction Lock + Duplicate Check"]
Lock --> |Conflict| ReturnError["Return Validation Error"]
Lock --> |Available| CreateRequest["Create Pending Request"]
CreateRequest --> NotifyValidator["Notify Validator"]
DirectWrite --> Success["Immediate Success"]
ReturnError --> Error["Validation Error"]
NotifyValidator --> ValidatorInbox["Validator Inbox"]
ValidatorInbox --> Approve["Approve Action"]
ValidatorInbox --> Reject["Reject Action"]
Approve --> AtomicWrite["Atomic Write to Data Tables"]
Reject --> NoChange["No Changes to Data"]
AtomicWrite --> UpdateStatus["Update Request Status"]
NoChange --> UpdateStatus
UpdateStatus --> Success
```

**Diagram sources**
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:110-132](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L110-L132)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:20-67](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L20-L67)

### Approval Processing Services
**New** Specialized services handle the atomic approval and rejection operations.

#### ApprovePengajuanPerubahanDataService
- **Atomic Operations**: All approvals happen within database transactions
- **Domain-Specific Writing**:
  - `profil_pribadi`: Updates allowed pegawai fields only
  - `keluarga_*`: Creates, updates, or deletes family records
- **Field Whitelisting**: Prevents unauthorized data modifications

#### RejectPengajuanPerubahanDataService  
- **Simple Operation**: Updates request status and stores rejection reason
- **No Data Changes**: Ensures original data integrity

**Section sources**
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:213-234](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L213-L234)
- [app/Http/Controllers/Kepegawaian/KeluargaController.php:65-91](file://app/Http/Controllers/Kepegawaian/KeluargaController.php#L65-L91)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:20-67](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L20-L67)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:13-134](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L13-L134)
- [app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php:12-66](file://app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php#L12-L66)
- [app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php:9-21](file://app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php#L9-L21)

## Dependency Analysis
The controller depends on the model and resource to assemble responses. The middleware enforces security policies before reaching the controller. Routes bind endpoints to controller actions with parameter constraints. **New approval workflow dependencies** include service layer for interception and approval processing.

```mermaid
graph LR
R["routes/api.php"] --> C["PegawaiApiController"]
C --> M["Pegawai model"]
C --> Res["PegawaiApiResource"]
M --> J["RefJabatan"]
M --> U["RefUnitKerja"]
M --> P["RefPangkat"]
R --> MW["VerifyHmacSignature"]
R --> S["Sanctum"]
AR["routes/web.php"] --> AC["ApprovalPengajuanPerubahanDataController"]
AC --> AM["PengajuanPerubahanData model"]
AC --> SM["SubmitPengajuanPerubahanDataService"]
AC --> AMS["ApprovePengajuanPerubahanDataService"]
AC --> RMS["RejectPengajuanPerubahanDataService"]
SM --> AM
AMS --> AM
RMS --> AM
AC --> MW2["Validator Middleware"]
```

**Diagram sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)
- [app/Http/Controllers/Api/PegawaiApiController.php:20-112](file://app/Http/Controllers/Api/PegawaiApiController.php#L20-L112)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:18-69](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L18-L69)

**Section sources**
- [routes/api.php:21-31](file://routes/api.php#L21-L31)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)
- [app/Http/Controllers/Api/PegawaiApiController.php:20-112](file://app/Http/Controllers/Api/PegawaiApiController.php#L20-L112)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:18-69](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L18-L69)

## Performance Considerations
- Eager loading: Controller loads related data (jabatan, unitKerja, pangkat) to avoid N+1 queries.
- Batch mode: Uses IN clause for efficient retrieval of multiple NIPs.
- Search limit: Caps results to 20 entries to control response size and latency.
- Rate limiting: Prevents abuse and ensures fair usage across clients.
- **New**: Approval workflow optimizations:
  - Transaction-level locking prevents duplicate pending requests
  - Scope key generation optimizes conflict detection
  - Atomic operations ensure data consistency

## Troubleshooting Guide
- 401 Unauthorized:
  - Confirm Sanctum token is included and valid.
  - Verify HMAC headers (X-Timestamp and X-Signature) are present and correct.
  - Ensure the shared secret matches the server configuration.
- 404 Not Found:
  - Validate the NIP format is exactly 18 digits.
  - Check that the NIP exists in the system.
- 422 Unprocessable Entity:
  - For batch mode: ensure nip[] length ≤ 50 and each NIP is 18 digits.
  - For search mode: verify query parameters and status values.
- **New Approval Workflow Issues**:
  - Duplicate pending requests: Check scope_key conflicts and transaction locks.
  - Operator interception: Verify user role is operator vs pegawai.
  - Validator access: Ensure proper iam.permission:pengajuan-perubahan.validate.
  - Approval failures: Check field whitelisting and domain support.

**Section sources**
- [app/Http/Middleware/VerifyHmacSignature.php:31-43](file://app/Http/Middleware/VerifyHmacSignature.php#L31-L43)
- [app/Http/Controllers/Api/PegawaiApiController.php:33-38](file://app/Http/Controllers/Api/PegawaiApiController.php#L33-L38)
- [tests/Feature/Api/PegawaiApiTest.php:37-81](file://tests/Feature/Api/PegawaiApiTest.php#L37-L81)
- [tests/Feature/Api/PegawaiApiTest.php:108-117](file://tests/Feature/Api/PegawaiApiTest.php#L108-L117)
- [tests/Feature/Api/PegawaiApiTest.php:133-143](file://tests/Feature/Api/PegawaiApiTest.php#L133-L143)
- [tests/Feature/Api/PegawaiApiTest.php:178-215](file://tests/Feature/Api/PegawaiApiTest.php#L178-L215)
- [tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php:98-147](file://tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php#L98-L147)

## Conclusion
The Employee Management API provides secure, standardized endpoints for retrieving employee data along with a comprehensive approval workflow system. By combining Sanctum authentication with HMAC signature verification and strict validation rules, it ensures reliable integration with external systems such as attendance QR systems. The new approval workflow enhances security by implementing operator interception mechanisms and validator-based approvals, providing complete audit trails and preventing unauthorized direct writes to sensitive data.

## Appendices

### Appendix A: Endpoint Reference
- GET /api/v1/pegawai/{nip}
  - Path parameter: nip (18 digits)
  - Query: none
  - Response: { data: Employee }
- GET /api/v1/pegawai
  - Query parameters:
    - nip[] (array, max 50, each 18 digits) — Batch mode
    - search (string) — Search mode
    - status (string, default aktif) — Search mode
  - Response (Batch): { data: [Employee...], not_found: [nip...] }
  - Response (Search): { data: [Employee...], meta: { total, per_page: 20 } }
- **New**: GET /kepegawaian/pengajuan
  - Response: { pengajuanList: [{id, domain, aksi, submitted_at, pengaju}] }
- **New**: GET /kepegawaian/pengajuan/{pengajuan}
  - Response: { pengajuan, diffItems }
- **New**: POST /kepegawaian/pengajuan/{pengajuan}/approve
  - Response: Success with immediate data changes
- **New**: POST /kepegawaian/pengajuan/{pengajuan}/reject
  - Response: Success with rejection reason stored

**Section sources**
- [routes/api.php:26-30](file://routes/api.php#L26-L30)
- [routes/web.php:162-171](file://routes/web.php#L162-L171)
- [app/Http/Controllers/Api/PegawaiApiController.php:52-110](file://app/Http/Controllers/Api/PegawaiApiController.php#L52-L110)
- [app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php:20-67](file://app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php#L20-L67)

### Appendix B: HMAC Signing Payload Construction
- Construct payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
- Sort query parameters by key.
- Compute SHA256 of request body (or empty string if none).
- Use shared secret from configuration.

**Section sources**
- [app/Http/Middleware/VerifyHmacSignature.php:46-55](file://app/Http/Middleware/VerifyHmacSignature.php#L46-L55)
- [config/kepegawaian.php:15](file://config/kepegawaian.php#L15)

### Appendix C: Approval Workflow Data Model
**New** The approval system uses a unified data model for tracking all change requests.

```mermaid
classDiagram
class PengajuanPerubahanData {
+string nomor_pengajuan
+string domain
+string aksi
+string status
+array before_payload
+array after_payload
+array changed_fields
+array lampiran_paths
+datetime submitted_at
+datetime approved_at
+datetime rejected_at
}
class SubmitPengajuanPerubahanDataService {
-handle(pengaju, payload, jenisPengaju) PengajuanPerubahanData
-resolveSubjectPegawaiId(payload) string
-resolveBeforePayload(payload) array
-makeScopeKey(subjectId, payload) string
}
class ApprovePengajuanPerubahanDataService {
-handle(pengajuan, validator) void
-applyKeluargaMutation(pengajuan) void
}
class RejectPengajuanPerubahanDataService {
-handle(pengajuan, validator, alasan) void
}
PengajuanPerubahanData --> SubmitPengajuanPerubahanDataService
PengajuanPerubahanData --> ApprovePengajuanPerubahanDataService
PengajuanPerubahanData --> RejectPengajuanPerubahanDataService
```

**Diagram sources**
- [app/Models/PengajuanPerubahanData.php:11-69](file://app/Models/PengajuanPerubahanData.php#L11-L69)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:13-134](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L13-L134)
- [app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php:12-66](file://app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php#L12-L66)
- [app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php:9-21](file://app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php#L9-L21)

**Section sources**
- [app/Models/PengajuanPerubahanData.php:11-69](file://app/Models/PengajuanPerubahanData.php#L11-L69)
- [app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php:13-134](file://app/Services/PengajuanPerubahanData/SubmitPengajuanPerubahanDataService.php#L13-L134)
- [app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php:12-66](file://app/Services/PengajuanPerubahanData/ApprovePengajuanPerubahanDataService.php#L12-L66)
- [app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php:9-21](file://app/Services/PengajuanPerubahanData/RejectPengajuanPerubahanDataService.php#L9-L21)

### Appendix D: Domain Classification Matrix
**New** Domain classification for different types of family relationships.

| Relationship Type | Domain |
|-------------------|---------|
| Suami / Istri | pasangan |
| Anak | anak |
| Ayah / IbuKandung | orang_tua |
| IbuTiri | orang_tua |
| AyahMertua | orang_tua |
| IbuMertua | orang_tua |
| Lainnya (Paman, Bibi, dll.) | keluarga_lain |

**Section sources**
- [app/Http/Controllers/Kepegawaian/KeluargaController.php:68-73](file://app/Http/Controllers/Kepegawaian/KeluargaController.php#L68-L73)
- [app/Http/Controllers/Kepegawaian/KeluargaController.php:107-112](file://app/Http/Controllers/Kepegawaian/KeluargaController.php#L107-L112)
- [app/Http/Controllers/Kepegawaian/KeluargaController.php:146-151](file://app/Http/Controllers/Kepegawaian/KeluargaController.php#L146-L151)