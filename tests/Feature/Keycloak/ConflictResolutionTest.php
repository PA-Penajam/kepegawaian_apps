<?php

/**
 * Unit tests untuk ConflictResolution service.
 *
 * Menguji deteksi konflik dan resolusi dengan kebijakan "Pegawai Wins"
 * antara data Pegawai dan Keycloak user.
 */

use App\Enums\StatusPegawai;
use App\Keycloak\DataTransferObjects\ConflictResult;
use App\Keycloak\Enums\ConflictPolicy;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Services\ConflictResolution;
use App\Models\Pegawai;

beforeEach(function () {
    $this->resolver = new ConflictResolution;
});

// === detectConflicts() ===

describe('detectConflicts - DataMismatch', function () {
    test('mendeteksi DataMismatch ketika email berbeda', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi.old@email.com',
            'firstName' => 'Budi',
            'lastName' => 'Santoso',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::DataMismatch);
    });

    test('mendeteksi DataMismatch ketika firstName berbeda', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi@pegawai.go.id',
            'firstName' => 'Budiman',
            'lastName' => 'Santoso',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::DataMismatch);
    });

    test('mendeteksi DataMismatch ketika lastName berbeda', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi@pegawai.go.id',
            'firstName' => 'Budi',
            'lastName' => 'Wijaya',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::DataMismatch);
    });

    test('tidak mendeteksi DataMismatch ketika semua data cocok', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi@pegawai.go.id',
            'firstName' => 'Budi',
            'lastName' => 'Santoso',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::DataMismatch);
    });
});

describe('detectConflicts - StatusConflict', function () {
    test('mendeteksi StatusConflict ketika Pegawai aktif tapi Keycloak disabled', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Ani Rahayu',
            'email' => 'ani@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-2',
            'username' => $pegawai->nip,
            'email' => 'ani@pegawai.go.id',
            'firstName' => 'Ani',
            'lastName' => 'Rahayu',
            'enabled' => false,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::StatusConflict);
    });

    test('mendeteksi StatusConflict ketika Pegawai non-aktif tapi Keycloak enabled', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Ani Rahayu',
            'email' => 'ani@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-2',
            'username' => $pegawai->nip,
            'email' => 'ani@pegawai.go.id',
            'firstName' => 'Ani',
            'lastName' => 'Rahayu',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::StatusConflict);
    });

    test('tidak mendeteksi StatusConflict ketika status sesuai', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Ani Rahayu',
            'email' => 'ani@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-2',
            'username' => $pegawai->nip,
            'email' => 'ani@pegawai.go.id',
            'firstName' => 'Ani',
            'lastName' => 'Rahayu',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::StatusConflict);
    });
});

describe('detectConflicts - RoleOverride', function () {
    test('mendeteksi RoleOverride ketika roles berbeda', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Doni Pratama',
            'email' => 'doni@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-3',
            'username' => $pegawai->nip,
            'email' => 'doni@pegawai.go.id',
            'firstName' => 'Doni',
            'lastName' => 'Pratama',
            'enabled' => true,
            'realmRoles' => ['admin', 'super_admin'],
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::RoleOverride);
    });

    test('tidak mendeteksi RoleOverride ketika roles sama', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Doni Pratama',
            'email' => 'doni@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-3',
            'username' => $pegawai->nip,
            'email' => 'doni@pegawai.go.id',
            'firstName' => 'Doni',
            'lastName' => 'Pratama',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::RoleOverride);
    });
});

describe('detectConflicts - IdentifierChange', function () {
    test('mendeteksi IdentifierChange ketika NIP berbeda dari username', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Siti Aminah',
            'email' => 'siti@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-4',
            'username' => '999999999999999999',
            'email' => 'siti@pegawai.go.id',
            'firstName' => 'Siti',
            'lastName' => 'Aminah',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toContain(ConflictType::IdentifierChange);
    });

    test('tidak mendeteksi IdentifierChange ketika username cocok NIP', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Siti Aminah',
            'email' => 'siti@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-4',
            'username' => $pegawai->nip,
            'email' => 'siti@pegawai.go.id',
            'firstName' => 'Siti',
            'lastName' => 'Aminah',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::IdentifierChange);
    });
});

describe('detectConflicts - tanpa konflik', function () {
    test('mengembalikan array kosong ketika semua data cocok', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi@pegawai.go.id',
            'firstName' => 'Budi',
            'lastName' => 'Santoso',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->toBeEmpty();
    });

    test('dapat mendeteksi multiple konflik sekaligus', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => '000000000000000000',
            'email' => 'budi.lama@email.com',
            'firstName' => 'Budiman',
            'lastName' => 'Santoso',
            'enabled' => false,
            'realmRoles' => ['nonexistent_role'],
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)
            ->toContain(ConflictType::DataMismatch)
            ->toContain(ConflictType::StatusConflict)
            ->toContain(ConflictType::RoleOverride)
            ->toContain(ConflictType::IdentifierChange)
            ->toHaveCount(4);
    });
});

describe('detectConflicts - purity (Req 8.5)', function () {
    test('tidak memutasi data Pegawai selama deteksi', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $originalNip = $pegawai->nip;
        $originalEmail = $pegawai->email;
        $originalNama = $pegawai->nama_lengkap;
        $originalStatus = $pegawai->status_pegawai;

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => '000000000000000000',
            'email' => 'other@email.com',
            'firstName' => 'Other',
            'lastName' => 'Name',
            'enabled' => false,
            'realmRoles' => ['different_role'],
        ];

        $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($pegawai->nip)->toBe($originalNip)
            ->and($pegawai->email)->toBe($originalEmail)
            ->and($pegawai->nama_lengkap)->toBe($originalNama)
            ->and($pegawai->status_pegawai)->toBe($originalStatus);
    });

    test('tidak memutasi data Keycloak user selama deteksi', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => '000000000000000000',
            'email' => 'other@email.com',
            'firstName' => 'Other',
            'lastName' => 'Name',
            'enabled' => false,
            'realmRoles' => ['different_role'],
        ];

        $originalKeycloakUser = $keycloakUser;

        $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($keycloakUser)->toBe($originalKeycloakUser);
    });
});

// === resolve() ===

describe('resolve - Pegawai Wins policy', function () {
    test('resolve DataMismatch menghasilkan data Pegawai sebagai resolved', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => $pegawai->nip,
            'email' => 'budi.lama@email.com',
            'firstName' => 'Budiman',
            'lastName' => 'Santosa',
            'enabled' => true,
            'realmRoles' => ['viewer'],
        ];

        $result = $this->resolver->resolve(ConflictType::DataMismatch, $pegawai, $keycloakUser);

        expect($result)->toBeInstanceOf(ConflictResult::class)
            ->and($result->type)->toBe(ConflictType::DataMismatch)
            ->and($result->policy)->toBe(ConflictPolicy::PegawaiWins)
            ->and($result->resolvedData['email'])->toBe('budi.baru@pegawai.go.id')
            ->and($result->resolvedData['firstName'])->toBe('Budi')
            ->and($result->resolvedData['lastName'])->toBe('Santoso');
    });

    test('resolve StatusConflict menghasilkan enabled sesuai status Pegawai', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Ani Rahayu',
            'email' => 'ani@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $keycloakUser = [
            'id' => 'kc-uuid-2',
            'username' => $pegawai->nip,
            'email' => 'ani@pegawai.go.id',
            'firstName' => 'Ani',
            'lastName' => 'Rahayu',
            'enabled' => false,
            'realmRoles' => ['viewer'],
        ];

        $result = $this->resolver->resolve(ConflictType::StatusConflict, $pegawai, $keycloakUser);

        expect($result->type)->toBe(ConflictType::StatusConflict)
            ->and($result->resolvedData['enabled'])->toBeTrue();
    });

    test('resolve RoleOverride menghasilkan roles dari Pegawai', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Doni Pratama',
            'email' => 'doni@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-3',
            'username' => $pegawai->nip,
            'email' => 'doni@pegawai.go.id',
            'firstName' => 'Doni',
            'lastName' => 'Pratama',
            'enabled' => true,
            'realmRoles' => ['admin', 'super_admin'],
        ];

        $result = $this->resolver->resolve(ConflictType::RoleOverride, $pegawai, $keycloakUser);

        expect($result->type)->toBe(ConflictType::RoleOverride)
            ->and($result->resolvedData['realmRoles'])->toBe(
                $pegawai->iamRoles->pluck('slug')->sort()->values()->all()
            );
    });

    test('resolve IdentifierChange menghasilkan username dari NIP Pegawai', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Siti Aminah',
            'email' => 'siti@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $keycloakUser = [
            'id' => 'kc-uuid-4',
            'username' => '000000000000000000',
            'email' => 'siti.lama@email.com',
            'firstName' => 'Siti',
            'lastName' => 'Aminah',
            'enabled' => true,
            'realmRoles' => ['viewer'],
        ];

        $result = $this->resolver->resolve(ConflictType::IdentifierChange, $pegawai, $keycloakUser);

        expect($result->type)->toBe(ConflictType::IdentifierChange)
            ->and($result->resolvedData['username'])->toBe($pegawai->nip)
            ->and($result->resolvedData['email'])->toBe('siti@pegawai.go.id');
    });
});

describe('resolve - ConflictResult structure', function () {
    test('ConflictResult berisi pegawaiData dengan format yang benar', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => '000000000000000000',
            'email' => 'old@email.com',
            'firstName' => 'Old',
            'lastName' => 'Name',
            'enabled' => false,
            'realmRoles' => ['admin'],
        ];

        $result = $this->resolver->resolve(ConflictType::DataMismatch, $pegawai, $keycloakUser);

        expect($result->pegawaiData)->toHaveKeys(['nip', 'email', 'firstName', 'lastName', 'enabled', 'roles'])
            ->and($result->pegawaiData['nip'])->toBe($pegawai->nip)
            ->and($result->pegawaiData['email'])->toBe('budi@pegawai.go.id')
            ->and($result->pegawaiData['firstName'])->toBe('Budi')
            ->and($result->pegawaiData['lastName'])->toBe('Santoso')
            ->and($result->pegawaiData['enabled'])->toBeTrue();
    });

    test('ConflictResult berisi keycloakData dengan format yang benar', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $keycloakUser = [
            'id' => 'kc-uuid-1',
            'username' => '000000000000000000',
            'email' => 'old@email.com',
            'firstName' => 'Old',
            'lastName' => 'Name',
            'enabled' => false,
            'realmRoles' => ['admin'],
        ];

        $result = $this->resolver->resolve(ConflictType::DataMismatch, $pegawai, $keycloakUser);

        expect($result->keycloakData)->toHaveKeys(['username', 'email', 'firstName', 'lastName', 'enabled', 'roles'])
            ->and($result->keycloakData['username'])->toBe('000000000000000000')
            ->and($result->keycloakData['email'])->toBe('old@email.com')
            ->and($result->keycloakData['firstName'])->toBe('Old')
            ->and($result->keycloakData['lastName'])->toBe('Name')
            ->and($result->keycloakData['enabled'])->toBeFalse()
            ->and($result->keycloakData['roles'])->toBe(['admin']);
    });

    test('ConflictResult berisi keycloakData kosong ketika null diberikan', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $result = $this->resolver->resolve(ConflictType::DataMismatch, $pegawai, null);

        expect($result->keycloakData)->toBeEmpty();
    });
});

// === getPolicy() ===

describe('getPolicy', function () {
    test('selalu mengembalikan PegawaiWins policy', function () {
        expect($this->resolver->getPolicy())->toBe(ConflictPolicy::PegawaiWins);
    });
});

// === nama_lengkap splitting ===

describe('nama_lengkap splitting', function () {
    test('nama dengan satu kata menghasilkan firstName saja', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Soekarno',
            'email' => 'soekarno@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        $keycloakUser = [
            'id' => 'kc-uuid-5',
            'username' => $pegawai->nip,
            'email' => 'soekarno@pegawai.go.id',
            'firstName' => 'Soekarno',
            'lastName' => '',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::DataMismatch);
    });

    test('nama dengan lebih dari dua kata menggunakan sisanya sebagai lastName', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Muhammad Abdul Halim',
            'email' => 'halim@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        // firstName = "Muhammad", lastName = "Abdul Halim"
        $keycloakUser = [
            'id' => 'kc-uuid-6',
            'username' => $pegawai->nip,
            'email' => 'halim@pegawai.go.id',
            'firstName' => 'Muhammad',
            'lastName' => 'Abdul Halim',
            'enabled' => true,
            'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
        ];

        $conflicts = $this->resolver->detectConflicts($pegawai, $keycloakUser);

        expect($conflicts)->not->toContain(ConflictType::DataMismatch);
    });
});
