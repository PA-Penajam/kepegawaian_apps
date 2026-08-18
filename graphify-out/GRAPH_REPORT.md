# Graph Report - kepegawaian_apps  (2026-08-18)

## Corpus Check
- Large corpus: 1163 files · ~843,311 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 7263 nodes · 21069 edges · 268 communities (228 shown, 40 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 1661 edges (avg confidence: 0.6)
- Token cost: 42,876 input · 1,842 output

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 174
- Community 211
- Community 212
- Community 213
- Community 214
- Community 215
- Community 216
- Community 217
- Community 218
- Community 219
- Community 220
- Community 221
- Community 222
- Community 223
- Community 224
- Community 225
- Community 226
- Community 227
- Community 228
- Community 229
- Community 230
- Community 231
- Community 232
- Community 233
- Community 254
- Community 255

## God Nodes (most connected - your core abstractions)
1. `Pegawai` - 546 edges
2. `cn()` - 175 edges
3. `CutiPengajuan` - 133 edges
4. `IamApplication` - 129 edges
5. `UsulanKenaikanPangkat` - 100 edges
6. `_update()` - 88 edges
7. `x()` - 87 edges
8. `_update()` - 86 edges
9. `Controller` - 84 edges
10. `Model` - 84 edges

## Surprising Connections (you probably didn't know these)
- `createTokenResultForConcurrency()` --references--> `TokenResult`  [EXTRACTED]
  tests/Feature/Keycloak/ConcurrentTokenRefreshTest.php → app/Keycloak/DataTransferObjects/TokenResult.php
- `createRandomTokenResultForMiddleware()` --references--> `TokenResult`  [EXTRACTED]
  tests/Feature/Keycloak/KeycloakMiddlewarePropertyTest.php → app/Keycloak/DataTransferObjects/TokenResult.php
- `makeSkAdminPegawai()` --calls--> `Pegawai`  [EXTRACTED]
  tests/Feature/Http/Controllers/UsulanKenaikanPangkat/SkAdminControllerTest.php → app/Models/Pegawai.php
- `createPegawaiListEntry()` --calls--> `Pegawai`  [EXTRACTED]
  tests/Feature/Kepegawaian/PegawaiSearchFilterTest.php → app/Models/Pegawai.php
- `signInAsPegawaiAdmin()` --calls--> `Pegawai`  [EXTRACTED]
  tests/Feature/Kepegawaian/PegawaiSearchFilterTest.php → app/Models/Pegawai.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Arsitektur Autentikasi Refactor** — pegawai_model, user_model_deleted, nip_login_credential, fortify_auth_provider [EXTRACTED 1.00]
- **Sistem RBAC Multi-Role** — ref_role_model, pegawai_role_pivot_table, multi_role_permission_system, ensure_role_middleware [EXTRACTED 1.00]
- **Batch Implementasi Fitur** — database_migration_task, frontend_component_updates, tdd_workflow [INFERRED 0.90]

## Communities (268 total, 40 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.01
Nodes (123): abutsStart(), Ac(), addControllers(), addPlugins(), addScales(), ae(), afterTickToLabelConversion(), beforeTickToLabelConversion() (+115 more)

### Community 1 - "Community 1"
Cohesion: 0.02
Nodes (42): Throwable, BerkasChecklistTemplate, Pegawai, static, PengajuanPerubahanData, ChecklistTemplatePolicy, CutiPengajuanPolicy, IamPermissionPolicy (+34 more)

### Community 2 - "Community 2"
Cohesion: 0.02
Nodes (121): A(), activateAttributeIfSupported(), appendStringToTextAtIndex(), applyBlockAttribute(), attachmentDidChangeUploadProgress(), attachmentIsManaged(), attributeChangedCallback(), Ca() (+113 more)

### Community 3 - "Community 3"
Cohesion: 0.03
Nodes (128): AppContent(), Props, AppHeader(), mainNavItems, Props, AppLogo(), AppLogoIcon(), AppShell() (+120 more)

### Community 4 - "Community 4"
Cohesion: 0.02
Nodes (66): GenerateSuratPengantarPdf, ChecklistKelengkapanBerubah, UsulanKpSkTerbit, BerkasBelumLengkapException, BerkasChecklistItem, LogOptions, BerkasChecklistSubmission, BerkasChecklistSubmissionItem (+58 more)

### Community 5 - "Community 5"
Cohesion: 0.04
Nodes (44): BerkasChecklistSubmission, BerkasChecklistTemplate, getActivitylogOptions(), LogOptions, CutiAlokasiTahunan, CutiJenisMaster, CutiJenisPerStatusPegawai, CutiKonfigurasi (+36 more)

### Community 6 - "Community 6"
Cohesion: 0.02
Nodes (115): Ft(), aa(), active(), addControllers(), addEventListener(), addPlugins(), addScales(), an() (+107 more)

### Community 7 - "Community 7"
Cohesion: 0.04
Nodes (108): Props, Props, Props, Props, ApiSecretModal(), ApiSecretModalProps, Button(), Dialog() (+100 more)

### Community 8 - "Community 8"
Cohesion: 0.03
Nodes (29): ExpireOverdueDraftsCommand, CancelTidakDiizinkanException, TransitionTidakValidException, CutiPengajuan, PengajuanDisetujui, PengajuanDitolak, PengajuanMenungguApproval, PengajuanMenungguVerifikasi (+21 more)

### Community 9 - "Community 9"
Cohesion: 0.04
Nodes (38): IamApplication, IamPermission, IamRole, IamRolePermission, IamUserRole, IamRoleFactory, PegawaiFactory, static (+30 more)

### Community 10 - "Community 10"
Cohesion: 0.02
Nodes (38): KeycloakEmergencyLoginLog, RefJenisDiklat, RefJenisHukumanDisiplin, RefJenisPenghargaan, BerkasChecklistItemFactory, BerkasChecklistTemplateFactory, CutiPengajuanFactory, static (+30 more)

### Community 11 - "Community 11"
Cohesion: 0.06
Nodes (71): $c(), me(), D(), E(), g(), H(), _i(), J() (+63 more)

### Community 12 - "Community 12"
Cohesion: 0.04
Nodes (79): ConfirmDeleteDialog(), DialogAdjustSaldo(), CrudLayoutProps, CrudTableColumn, CrudTableProps, LaravelLink, PaginationMeta, PaginationWrapper() (+71 more)

### Community 13 - "Community 13"
Cohesion: 0.04
Nodes (108): Aa(), af(), ai(), An(), ao(), bf(), bl(), bo() (+100 more)

### Community 14 - "Community 14"
Cohesion: 0.03
Nodes (29): ActivityLogController, Controller, AuditController, PdfController, DashboardController, AplikasiController, UserAksesController, SelfServiceController (+21 more)

### Community 15 - "Community 15"
Cohesion: 0.04
Nodes (35): AuditSlugsCommand, KeycloakHealthCommand, NotifikasiDeadlineUsulanKp, SendKenaikanPangkatNotification, SendKgbNotification, RiwayatPangkatController, SinkronkanRiwayatPangkat, RiwayatPangkat (+27 more)

### Community 16 - "Community 16"
Cohesion: 0.04
Nodes (72): KartuSaldo(), Props, DashboardDistribusiSkeleton(), DashboardHeader(), PageProps, DashboardHeavySection(), COLORS, CustomTooltipProps (+64 more)

### Community 17 - "Community 17"
Cohesion: 0.04
Nodes (31): emailRules(), nipRules(), pegawaiRules(), RefJabatan, RefPangkat, RefUnitKerja, RefJabatanFactory, RiwayatJabatanFactory (+23 more)

### Community 18 - "Community 18"
Cohesion: 0.03
Nodes (20): ApprovalController, StoreChecklistTemplateRequest, UpdateChecklistTemplateRequest, UpdatePermissionRequest, StoreRiwayatPangkatRequest, UpdateFotoPegawaiRequest, UpdatePenghargaanRequest, UpdateRiwayatPangkatRequest (+12 more)

### Community 19 - "Community 19"
Cohesion: 0.06
Nodes (96): be(), _a(), Ac(), Ae(), ar(), as(), Ba(), Bc() (+88 more)

### Community 20 - "Community 20"
Cohesion: 0.05
Nodes (98): aa(), ai(), ar(), At(), r(), beforeDatasetDraw(), bf(), bu() (+90 more)

### Community 21 - "Community 21"
Cohesion: 0.04
Nodes (29): KeycloakAuthController, buildAuthorizationUrl(), exchangeCode(), refreshToken(), silentCheck(), validateIdToken(), rotateTokens(), storeTokens() (+21 more)

### Community 22 - "Community 22"
Cohesion: 0.05
Nodes (42): Handler, EmergencyLoginController, SsoController, HandleAppearance, HandleInertiaRequests, VerifyHmacSignature, Response, VerifyIamPermission (+34 more)

### Community 23 - "Community 23"
Cohesion: 0.04
Nodes (88): addBox(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterDatasetsUpdate(), afterFit(), afterSetDimensions(), afterUpdate() (+80 more)

### Community 24 - "Community 24"
Cohesion: 0.05
Nodes (87): addAttribute(), addAttributeAtRange(), addAttributesAtRange(), addHTMLAttribute(), appendText(), applyBlockAttributeAtRange(), canBeGroupedWith(), canDecreaseBlockAttributeLevel() (+79 more)

### Community 25 - "Community 25"
Cohesion: 0.04
Nodes (16): ApprovalController, PermissionController, RoleController, DokumenPegawaiController, HukumanDisiplinController, PenghargaanController, ApproveRequest, ReassignApproverRequest (+8 more)

### Community 26 - "Community 26"
Cohesion: 0.04
Nodes (81): after(), before(), contains(), count(), defaultZone(), Di(), diff(), diffNow() (+73 more)

### Community 27 - "Community 27"
Cohesion: 0.06
Nodes (53): AlertError(), FormPengajuan(), DeleteUser(), Heading(), InputError(), PasswordInput(), Props, TextLink() (+45 more)

### Community 28 - "Community 28"
Cohesion: 0.05
Nodes (21): PengajuanController, SaldoController, IamController, PegawaiApiController, UsulanKenaikanPangkatApiController, NotificationController, PengajuanResource, SaldoResource (+13 more)

### Community 29 - "Community 29"
Cohesion: 0.05
Nodes (55): ba(), bi(), c(), ca(), clickPercent(), constructor(), de(), define() (+47 more)

### Community 30 - "Community 30"
Cohesion: 0.05
Nodes (55): DialogApprove(), handleSubmit(), getActionLabel(), getApproveUrl(), DialogCancel(), DialogReject(), getStateIcon(), Props (+47 more)

### Community 31 - "Community 31"
Cohesion: 0.06
Nodes (72): breakFormattedBlock(), breaksOnReturn(), canSetCurrentAttribute(), canSetCurrentBlockAttribute(), canSetCurrentTextAttribute(), copyWithoutText(), createCaptionElement(), decreaseBlockAttributeLevel() (+64 more)

### Community 32 - "Community 32"
Cohesion: 0.12
Nodes (71): ad(), at(), B(), he(), br(), Bt(), X(), ca() (+63 more)

### Community 33 - "Community 33"
Cohesion: 0.06
Nodes (63): De(), Ae(), ai(), at(), B(), co(), de(), ds() (+55 more)

### Community 34 - "Community 34"
Cohesion: 0.06
Nodes (70): adjustHitBoxes(), afterDraw(), bc(), Bl(), c(), clear(), _computeLabelArea(), _computeTitleHeight() (+62 more)

### Community 35 - "Community 35"
Cohesion: 0.05
Nodes (69): ac(), Ai(), Ao(), applyStack(), ar(), as(), aspectRatio(), ca() (+61 more)

### Community 36 - "Community 36"
Cohesion: 0.07
Nodes (46): Props, SaldoData, DataTableToolbarProps, EnumOption, EnumSelect(), EnumSelectProps, MultiStepForm(), Collapsible() (+38 more)

### Community 37 - "Community 37"
Cohesion: 0.04
Nodes (59): _a(), Ao(), chartOptionScopes(), clone(), co(), constructor(), create(), dtFormatter() (+51 more)

### Community 38 - "Community 38"
Cohesion: 0.07
Nodes (59): adjustHitBoxes(), afterDraw(), ah(), aspectRatio(), ba(), beforeDatasetsDraw(), beforeDraw(), Bn() (+51 more)

### Community 39 - "Community 39"
Cohesion: 0.05
Nodes (59): afterAutoSkip(), applyStack(), au(), buildLookupTable(), _calculateBarIndexPixels(), _calculateBarValuePixels(), _computeAngle(), countVisibleElements() (+51 more)

### Community 40 - "Community 40"
Cohesion: 0.05
Nodes (12): Bi(), bn(), Id(), ji(), kd(), on(), qd(), Ri() (+4 more)

### Community 41 - "Community 41"
Cohesion: 0.12
Nodes (55): getType(), Cn(), b(), Bt(), Ct(), dn(), Dt(), Ft() (+47 more)

### Community 42 - "Community 42"
Cohesion: 0.05
Nodes (55): add(), attachFiles(), beforeinput(), canApplyToDocument(), compositionend(), compositionstart(), compositionupdate(), constructor() (+47 more)

### Community 43 - "Community 43"
Cohesion: 0.05
Nodes (47): Tabs(), TabsContent(), TabsList(), tabsListVariants, TabsTrigger(), actionTitle(), ApprovalAction, ApprovalInboxPage() (+39 more)

### Community 44 - "Community 44"
Cohesion: 0.07
Nodes (11): KenaikanPangkatMonitoringExport, KgbMonitoringExport, DashboardStatService, IamPermissionAuditor, Illuminate\Container\Container, Illuminate\Support\Collection, Maatwebsite\Excel\Concerns\Exportable, Maatwebsite\Excel\Concerns\FromCollection (+3 more)

### Community 45 - "Community 45"
Cohesion: 0.06
Nodes (52): Af(), an(), bi(), bl(), buildTicks(), calculateCircumference(), calculateLabelRotation(), _calculatePadding() (+44 more)

### Community 46 - "Community 46"
Cohesion: 0.06
Nodes (15): AlokasiTidakAdaException, CrossYearLeaveException, OverlapPengajuanException, SaldoTidakCukupException, SubmitTerlambatException, PengajuanController, SaldoController, SubmitPengajuanRequest (+7 more)

### Community 47 - "Community 47"
Cohesion: 0.05
Nodes (13): DiajukanState, DicabutSetelahDisetujuiState, DisetujuiAtasanState, DisetujuiState, DitolakAtasanState, DitolakKepegawaianState, DitolakPejabatState, DiverifikasiState (+5 more)

### Community 48 - "Community 48"
Cohesion: 0.06
Nodes (47): backspace(), createLinkHTML(), cut(), d(), delete(), deleteByComposition(), deleteByCut(), deleteByDrag() (+39 more)

### Community 49 - "Community 49"
Cohesion: 0.06
Nodes (23): actions(), button(), constructor(), danger(), dispatch(), dispatchSelf(), dispatchTo(), duration() (+15 more)

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (43): ar(), q(), Bi(), I(), c(), H(), d(), di() (+35 more)

### Community 51 - "Community 51"
Cohesion: 0.06
Nodes (20): CreateNewUser, ResetUserPassword, emailRules(), nameRules(), profileRules(), SecurityController, PasswordUpdateRequest, ProfileDeleteRequest (+12 more)

### Community 52 - "Community 52"
Cohesion: 0.08
Nodes (45): applyKeyboardCommand(), attachmentDidChangeAttributes(), attachmentEditorDidRequestRemovalOfAttachment(), canBeGrouped(), checkValidity(), copyUsingObjectMap(), copyUsingObjectsFromDocument(), createContentNodes() (+37 more)

### Community 53 - "Community 53"
Cohesion: 0.07
Nodes (44): acquireContext(), ad(), addElements(), al(), buildOrUpdateElements(), Cc(), cd(), Ce() (+36 more)

### Community 54 - "Community 54"
Cohesion: 0.07
Nodes (44): acquireContext(), addElements(), bindEvents(), bindUserEvents(), buildOrUpdateElements(), buildOrUpdateScales(), _checkEventBindings(), cl() (+36 more)

### Community 55 - "Community 55"
Cohesion: 0.07
Nodes (44): add(), afterAutoSkip(), Bi(), buildLookupTable(), C(), Co(), data(), determineDataLimits() (+36 more)

### Community 56 - "Community 56"
Cohesion: 0.07
Nodes (44): afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterFit(), afterSetDimensions(), afterTickToLabelConversion(), afterUpdate(), beforeBuildTicks() (+36 more)

### Community 57 - "Community 57"
Cohesion: 0.07
Nodes (43): buildTicks(), calculateCircumference(), calculateLabelRotation(), _calculatePadding(), _circumference(), _computeAngle(), _computeLabelItems(), _computeLabelSizes() (+35 more)

### Community 58 - "Community 58"
Cohesion: 0.07
Nodes (9): KeluargaController, PegawaiController, StoreKeluargaRequest, StorePegawaiRequest, UpdateKeluargaRequest, UpdatePegawaiRequest, SubmitPengajuanPerubahanDataService, Intervention\Image\Drivers\Gd\Driver (+1 more)

### Community 59 - "Community 59"
Cohesion: 0.06
Nodes (42): cacheViewForObject(), canSyncDocumentView(), compositionDidChangeDocument(), compositionDidLoadSnapshot(), createAttachmentNodes(), createChildView(), createContainerElement(), createDocumentFragmentForSync() (+34 more)

### Community 60 - "Community 60"
Cohesion: 0.07
Nodes (31): Checkbox(), Circle, hexToRgb(), MousePosition, Particles(), ParticlesProps, Separator(), ShimmerButton (+23 more)

### Community 61 - "Community 61"
Cohesion: 0.07
Nodes (40): alpha(), be(), beforeDraw(), darken(), desaturate(), ea(), en(), fe() (+32 more)

### Community 62 - "Community 62"
Cohesion: 0.07
Nodes (37): DataTableToolbar(), DataTableToolbarFilter, BadgeVariant, breadcrumbs, getSortIndicator(), getStatusBadgeVariant(), PegawaiIndex(), PegawaiIndexFilters (+29 more)

### Community 63 - "Community 63"
Cohesion: 0.07
Nodes (38): add(), As(), bh(), _cachedScopes(), createResolver(), cu(), Ea(), get() (+30 more)

### Community 64 - "Community 64"
Cohesion: 0.07
Nodes (39): afterDatasetsUpdate(), buildOrUpdateControllers(), datasetAnimationScopeKeys(), _destroyDatasetMeta(), generateLabels(), getController(), getDatasetMeta(), getDataVisibility() (+31 more)

### Community 65 - "Community 65"
Cohesion: 0.05
Nodes (37): class-variance-authority, clsx, globals, @headlessui/react, dependencies, class-variance-authority, clsx, globals (+29 more)

### Community 66 - "Community 66"
Cohesion: 0.08
Nodes (37): canAcceptDataTransfer(), canDecreaseNestingLevel(), canIncreaseNestingLevel(), compositionControllerDidFocus(), compositionDidRequestChangingSelectionToLocationRange(), createDOMRangeFromPoint(), createLocationRangeFromDOMRange(), decreaseNestingLevel() (+29 more)

### Community 67 - "Community 67"
Cohesion: 0.08
Nodes (37): _a(), al(), ba(), _cachedScopes(), cancel(), _createDescriptors(), createResolver(), datasetElementScopeKeys() (+29 more)

### Community 68 - "Community 68"
Cohesion: 0.13
Nodes (8): detectConflicts(), getPolicy(), resolve(), ConflictResult, ConflictResolution, ConflictPolicy, ConflictType, Illuminate\Support\Facades\Http

### Community 69 - "Community 69"
Cohesion: 0.08
Nodes (16): KeycloakEmergencyLoginLogResource, App\Filament\Resources\KeycloakEmergencyLoginLogResource\Pages, ListKeycloakEmergencyLoginLogs, KeycloakSyncAuditResource, App\Filament\Resources\KeycloakSyncAuditResource\Pages, ListKeycloakSyncAudits, ViewKeycloakSyncAudit, Filament\Forms (+8 more)

### Community 70 - "Community 70"
Cohesion: 0.15
Nodes (35): _a(), aa(), ba(), Be(), br(), Ca(), ce(), Cr() (+27 more)

### Community 71 - "Community 71"
Cohesion: 0.07
Nodes (9): KeycloakCircuitResetCommand, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Auth\Notifications\VerifyEmail, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Notification, Illuminate\Support\Facades\RateLimiter, Laravel\Fortify\Features, createCircuitBreakerMock() (+1 more)

### Community 72 - "Community 72"
Cohesion: 0.06
Nodes (33): babel-plugin-react-compiler, eslint-config-prettier, eslint-import-resolver-typescript, @eslint/js, eslint-plugin-import, eslint-plugin-react, eslint-plugin-react-hooks, @laravel/vite-plugin-wayfinder (+25 more)

### Community 73 - "Community 73"
Cohesion: 0.12
Nodes (26): Props, Alert(), AlertDescription(), AlertTitle(), alertVariants, AlertDialog(), AlertDialogAction(), AlertDialogCancel() (+18 more)

### Community 74 - "Community 74"
Cohesion: 0.14
Nodes (32): al(), Dn(), En(), Eo(), es(), fi(), gd(), Ha() (+24 more)

### Community 75 - "Community 75"
Cohesion: 0.08
Nodes (31): bd(), Br(), bt(), dg(), em(), eras(), extract(), hl() (+23 more)

### Community 76 - "Community 76"
Cohesion: 0.09
Nodes (14): a(), ar(), b(), cr(), d(), f(), H(), ji() (+6 more)

### Community 77 - "Community 77"
Cohesion: 0.14
Nodes (28): apply(), da(), ei(), fa(), Fi(), fn(), S(), h() (+20 more)

### Community 78 - "Community 78"
Cohesion: 0.14
Nodes (10): KeycloakSyncCommand, fullSync(), healthCheck(), incrementalSync(), syncPegawai(), getAccessTokenExpiry(), HealthStatus, SyncResult (+2 more)

### Community 79 - "Community 79"
Cohesion: 0.12
Nodes (6): KeycloakSyncAudit, SyncAuditLogger, KeycloakSyncAuditFactory, static, generateRandomConflictType(), ConflictType

### Community 80 - "Community 80"
Cohesion: 0.09
Nodes (27): attachmentForFile(), attributesForFile(), compositionShouldAcceptFile(), didChangeAttributes(), getContentType(), getCurrentTextAttributes(), getHeight(), getHref() (+19 more)

### Community 81 - "Community 81"
Cohesion: 0.10
Nodes (26): ArrowLeft(), ArrowRight(), attachmentManagerDidRequestRemovalOfAttachment(), compositionControllerDidRequestRemovalOfAttachment(), createDOMRangeFromLocationRange(), editAttachment(), expandSelectionInDirection(), findContainerAndOffsetFromLocation() (+18 more)

### Community 82 - "Community 82"
Cohesion: 0.11
Nodes (24): addEventListener(), bindResponsiveEvents(), Fn(), getAllParsedValues(), getDataTimestamps(), _getLabelBounds(), getLabelTimestamps(), getMatchingVisibleMetas() (+16 more)

### Community 83 - "Community 83"
Cohesion: 0.11
Nodes (24): alpha(), Gc(), greyscale(), hslString(), _i(), ih(), Jc(), jo() (+16 more)

### Community 84 - "Community 84"
Cohesion: 0.13
Nodes (7): DispatchPendingEventsCommand, CutiEvent, CutiEventDelivery, ConsumerRegistry, EventDispatcherService, Illuminate\Database\Eloquent\Concerns\HasUuids, buatPengajuanUntukOutbox()

### Community 85 - "Community 85"
Cohesion: 0.20
Nodes (3): KeycloakCircuitBreaker, CircuitState, executeOperation()

### Community 86 - "Community 86"
Cohesion: 0.19
Nodes (19): B(), C(), G(), H(), I(), J(), O(), U() (+11 more)

### Community 87 - "Community 87"
Cohesion: 0.18
Nodes (17): AppearanceToggleTab(), Appearance, applyTheme(), getStoredAppearance(), handleSystemThemeChange(), initializeTheme(), isDarkMode(), listeners (+9 more)

### Community 88 - "Community 88"
Cohesion: 0.15
Nodes (5): NomorSuratReservation, NomorSuratSequence, reserve(), PlaceholderNomorSuratService, NomorSuratService

### Community 89 - "Community 89"
Cohesion: 0.11
Nodes (17): AdminPanelProvider, Filament\Http\Middleware\Authenticate, Filament\Http\Middleware\AuthenticateSession, Filament\Http\Middleware\DisableBladeIconComponents, Filament\Http\Middleware\DispatchServingFilamentEvent, Filament\Pages, Filament\PanelProvider, Filament\Support\Colors\Color (+9 more)

### Community 90 - "Community 90"
Cohesion: 0.18
Nodes (20): It(), appendAttachmentWithAttributes(), appendBlockForAttributesWithElement(), appendBlockForElement(), appendBlockForTextNode(), appendEmptyBlock(), appendPiece(), appendStringWithAttributes() (+12 more)

### Community 91 - "Community 91"
Cohesion: 0.12
Nodes (20): actionIsExternal(), canInvokeAction(), compositionControllerDidBlur(), compositionControllerDidSyncDocumentView(), compositionDidAddAttachment(), compositionDidChangeAttachmentPreviewURL(), compositionDidChangeCurrentAttributes(), compositionDidEditAttachment() (+12 more)

### Community 92 - "Community 92"
Cohesion: 0.11
Nodes (20): box(), canBeConsolidatedWith(), canRedo(), canUndo(), compositionControllerDidRender(), createEntry(), fromUCS2String(), getTargetDOMRange() (+12 more)

### Community 93 - "Community 93"
Cohesion: 0.14
Nodes (20): average(), Ch(), dataset(), ee(), Fe(), ge(), getCenterPoint(), hasValue() (+12 more)

### Community 94 - "Community 94"
Cohesion: 0.10
Nodes (19): resources/js/**/*.d.ts, resources/js/**/*.ts, resources/js/**/*.tsx, compilerOptions, allowJs, baseUrl, esModuleInterop, forceConsistentCasingInFileNames (+11 more)

### Community 95 - "Community 95"
Cohesion: 0.20
Nodes (7): Action, SyncDashboard, KeycloakSyncState, Filament\Actions\Action, Filament\Forms\Components\TextInput, Filament\Notifications\Notification, Filament\Pages\Page

### Community 96 - "Community 96"
Cohesion: 0.19
Nodes (4): Request, IamSecretRecoveryController, IamSecretService, Illuminate\Contracts\Cache\Repository

### Community 97 - "Community 97"
Cohesion: 0.15
Nodes (7): SsoAwareLoginResponse, FortifyServiceProvider, KeycloakServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\ServiceProvider, Laravel\Fortify\Contracts\LoginResponse, Laravel\Fortify\Fortify

### Community 99 - "Community 99"
Cohesion: 0.14
Nodes (19): average(), br(), Ct(), dn(), getCenterPoint(), getProps(), hasValue(), ii() (+11 more)

### Community 100 - "Community 100"
Cohesion: 0.16
Nodes (3): ProcessCarryOverCommand, CutiSaldoLedger, CarryOverProcessorService

### Community 101 - "Community 101"
Cohesion: 0.14
Nodes (5): ApprovalPengajuanPerubahanDataController, ApprovePengajuanPerubahanDataRequest, RejectPengajuanPerubahanDataRequest, PengajuanPerubahanDataDiffService, RejectPengajuanPerubahanDataService

### Community 102 - "Community 102"
Cohesion: 0.18
Nodes (4): RefJenisDokumenController, StoreRefJenisDokumenRequest, RefJenisDokumen, RefJenisDokumenSeeder

### Community 103 - "Community 103"
Cohesion: 0.18
Nodes (4): RefStatusKepegawaianController, StoreRefStatusKepegawaianRequest, RefStatusKepegawaian, RefStatusKepegawaianSeeder

### Community 104 - "Community 104"
Cohesion: 0.18
Nodes (4): RefStatusPegawaiController, StoreRefStatusPegawaiRequest, RefStatusPegawai, RefStatusPegawaiSeeder

### Community 105 - "Community 105"
Cohesion: 0.11
Nodes (17): aliases, components, hooks, lib, ui, utils, iconLibrary, rsc (+9 more)

### Community 106 - "Community 106"
Cohesion: 0.13
Nodes (18): Database Migration Task, EnsureRole Middleware, Fortify Authentication Provider, Frontend Component Updates, HandleInertiaRequests Middleware, Sistem Permission Multi-Role, NIP Login Credential, Tabel password_reset_tokens (+10 more)

### Community 108 - "Community 108"
Cohesion: 0.15
Nodes (3): RefRoleController, StoreRefRoleRequest, UpdateRefRoleRequest

### Community 109 - "Community 109"
Cohesion: 0.12
Nodes (16): autoload-dev, psr-4, description, extra, laravel, keywords, dont-discover, license (+8 more)

### Community 111 - "Community 111"
Cohesion: 0.12
Nodes (16): allowScripts, esbuild@0.27.4, puppeteer@24.42.0, private, $schema, scripts, build, build:ssr (+8 more)

### Community 112 - "Community 112"
Cohesion: 0.14
Nodes (3): RiwayatJabatanController, StoreRiwayatJabatanRequest, UpdateRiwayatJabatanRequest

### Community 114 - "Community 114"
Cohesion: 0.12
Nodes (16): require, darkaonline/l5-swagger, filament/filament, inertiajs/inertia-laravel, intervention/image, jumbojett/openid-connect-php, laravel/fortify, laravel/framework (+8 more)

### Community 115 - "Community 115"
Cohesion: 0.13
Nodes (4): [g](), style(), update(), [x]()

### Community 116 - "Community 116"
Cohesion: 0.16
Nodes (4): scopeFilter(), scopeSearch(), scopeSorted(), Illuminate\Database\Eloquent\Builder

### Community 117 - "Community 117"
Cohesion: 0.17
Nodes (15): active(), _animateOptions(), cancel(), _createAnimations(), _createDescriptors(), _descriptors(), ed(), _notify() (+7 more)

### Community 118 - "Community 118"
Cohesion: 0.14
Nodes (14): scripts, lint, lint:check, post-update-cmd, pre-package-uninstall, test, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall, @lint:check (+6 more)

### Community 119 - "Community 119"
Cohesion: 0.16
Nodes (13): di(), e(), g(), Ht(), i(), Ie(), Me(), Re() (+5 more)

### Community 120 - "Community 120"
Cohesion: 0.20
Nodes (14): At(), dataset(), _eventHandler(), Fa(), getMaximumSize(), index(), isPointInArea(), Ls() (+6 more)

### Community 121 - "Community 121"
Cohesion: 0.19
Nodes (3): RiwayatDiklatController, StoreRiwayatDiklatRequest, UpdateRiwayatDiklatRequest

### Community 122 - "Community 122"
Cohesion: 0.19
Nodes (3): RiwayatPendidikanController, StoreRiwayatPendidikanRequest, UpdateRiwayatPendidikanRequest

### Community 123 - "Community 123"
Cohesion: 0.15
Nodes (13): lightningcss-linux-x64-gnu, lightningcss-win32-x64-msvc, optionalDependencies, lightningcss-linux-x64-gnu, lightningcss-win32-x64-msvc, @rollup/rollup-linux-x64-gnu, @rollup/rollup-win32-x64-msvc, @tailwindcss/oxide-linux-x64-gnu (+5 more)

### Community 124 - "Community 124"
Cohesion: 0.18
Nodes (13): didMutate(), findSignificantMutations(), getEndData(), getMutationsByType(), getMutationSummary(), getTextChangesFromCharacterData(), getTextChangesFromChildList(), getTextMutationSummary() (+5 more)

### Community 125 - "Community 125"
Cohesion: 0.17
Nodes (12): Be(), ei(), ii(), le(), ni(), oi(), r(), ri() (+4 more)

### Community 126 - "Community 126"
Cohesion: 0.24
Nodes (8): e(), i(), l(), Ni(), o(), t(), u(), Oi()

### Community 127 - "Community 127"
Cohesion: 0.18
Nodes (3): NullSikepAdapter, UsulanKenaikanPangkatDto, SikepAdapter

### Community 128 - "Community 128"
Cohesion: 0.18
Nodes (11): ci:check, dev, dev:ssr, Composer\\Config::disableProcessTimeout, npm run build:ssr, npm run format:check, npm run lint:check, npm run types:check (+3 more)

### Community 130 - "Community 130"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+2 more)

### Community 131 - "Community 131"
Cohesion: 0.20
Nodes (9): command, enabled, type, mcp, laravel-boost, $schema, artisan, boost:mcp (+1 more)

### Community 132 - "Community 132"
Cohesion: 0.22
Nodes (9): Ce(), Dt(), Fe(), He(), ir(), Mt(), nr(), rt() (+1 more)

### Community 133 - "Community 133"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 134 - "Community 134"
Cohesion: 0.38
Nodes (3): DuplicateUsulanException, PegawaiTidakEligibleException, RuntimeException

### Community 136 - "Community 136"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 137 - "Community 137"
Cohesion: 0.52
Nodes (6): generateKeycloakUserWithDataMismatch(), generateKeycloakUserWithIdentifierChange(), generateKeycloakUserWithRoleOverride(), generateKeycloakUserWithStatusConflict(), generateRandomConflictingKeycloakUser(), generateRandomDifferentEmail()

### Community 138 - "Community 138"
Cohesion: 0.40
Nodes (5): npx, flyenv, magicuidesign-mcp, shadcn, @magicuidesign/mcp

### Community 144 - "Community 144"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 145 - "Community 145"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 149 - "Community 149"
Cohesion: 0.50
Nodes (4): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan filament:upgrade, @php artisan package:discover --ansi

### Community 150 - "Community 150"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 151 - "Community 151"
Cohesion: 0.50
Nodes (3): Maatwebsite\Excel\DefaultValueBinder, Maatwebsite\Excel\Excel, PhpOffice\PhpSpreadsheet\Reader\Csv

## Knowledge Gaps
- **470 isolated node(s):** `php`, `@magicuidesign/mcp`, `flyenv`, `$schema`, `style` (+465 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **40 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Pegawai` connect `Community 1` to `Community 4`, `Community 5`, `Community 8`, `Community 9`, `Community 10`, `Community 137`, `Community 14`, `Community 15`, `Community 17`, `Community 18`, `Community 21`, `Community 22`, `Community 25`, `Community 28`, `Community 44`, `Community 46`, `Community 47`, `Community 51`, `Community 58`, `Community 68`, `Community 69`, `Community 71`, `Community 78`, `Community 79`, `Community 84`, `Community 95`, `Community 97`, `Community 100`, `Community 108`, `Community 112`, `Community 113`, `Community 116`, `Community 121`, `Community 122`?**
  _High betweenness centrality (0.156) - this node is a cross-community bridge._
- **Why does `StatusKepegawaian` connect `Community 17` to `Community 62`?**
  _High betweenness centrality (0.094) - this node is a cross-community bridge._
- **Why does `Br()` connect `Community 75` to `Community 32`, `Community 0`, `Community 42`, `Community 52`, `Community 20`, `Community 119`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **What connects `php`, `@magicuidesign/mcp`, `flyenv` to the rest of the system?**
  _470 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.01145311381531854 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.01686548745372275 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.016435888776314307 - nodes in this community are weakly interconnected._