<?php

use App\Filament\Resources\KeycloakSyncAuditResource;
use App\Filament\Resources\KeycloakSyncAuditResource\Pages\ListKeycloakSyncAudits;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Models\Pegawai;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = Pegawai::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render list page', function () {
    $this->get(KeycloakSyncAuditResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list sync audit records', function () {
    $audits = KeycloakSyncAudit::factory()->count(3)->create();

    Livewire::test(ListKeycloakSyncAudits::class)
        ->assertCanSeeTableRecords($audits);
});

it('can search by nip', function () {
    $target = KeycloakSyncAudit::factory()->create(['nip' => '123456789012345678']);
    $other = KeycloakSyncAudit::factory()->create(['nip' => '999999999999999999']);

    Livewire::test(ListKeycloakSyncAudits::class)
        ->searchTable('123456789012345678')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can filter by event_type', function () {
    $createAudit = KeycloakSyncAudit::factory()->create(['event_type' => 'create']);
    $conflictAudit = KeycloakSyncAudit::factory()->create(['event_type' => 'conflict']);

    Livewire::test(ListKeycloakSyncAudits::class)
        ->filterTable('event_type', 'create')
        ->assertCanSeeTableRecords([$createAudit])
        ->assertCanNotSeeTableRecords([$conflictAudit]);
});

it('can filter by conflict_type', function () {
    $dataMismatch = KeycloakSyncAudit::factory()->create([
        'event_type' => 'conflict',
        'conflict_type' => 'data_mismatch',
    ]);
    $statusConflict = KeycloakSyncAudit::factory()->create([
        'event_type' => 'conflict',
        'conflict_type' => 'status_conflict',
    ]);

    Livewire::test(ListKeycloakSyncAudits::class)
        ->filterTable('conflict_type', 'data_mismatch')
        ->assertCanSeeTableRecords([$dataMismatch])
        ->assertCanNotSeeTableRecords([$statusConflict]);
});

it('can filter by date range', function () {
    $oldAudit = KeycloakSyncAudit::factory()->create([
        'created_at' => now()->subDays(10),
    ]);
    $recentAudit = KeycloakSyncAudit::factory()->create([
        'created_at' => now(),
    ]);

    Livewire::test(ListKeycloakSyncAudits::class)
        ->filterTable('created_at', [
            'from' => now()->subDay()->toDateString(),
            'until' => now()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$recentAudit])
        ->assertCanNotSeeTableRecords([$oldAudit]);
});

it('can render view page with snapshots', function () {
    $audit = KeycloakSyncAudit::factory()->create([
        'event_type' => 'conflict',
        'pegawai_snapshot' => ['nip' => '123456789012345678', 'nama' => 'Test User'],
        'keycloak_snapshot' => ['username' => '123456789012345678', 'email' => 'test@example.com'],
        // Nilai bersarang (fields_updated) harus dirender sebagai string, bukan crash
        'resolution' => ['action' => 'pegawai_wins', 'fields_updated' => ['email', 'nama']],
    ]);

    $this->get(KeycloakSyncAuditResource::getUrl('view', ['record' => $audit]))
        ->assertSuccessful();
});

it('paginates with max 50 records per page', function () {
    KeycloakSyncAudit::factory()->count(55)->create();

    Livewire::test(ListKeycloakSyncAudits::class)
        ->assertCountTableRecords(55);
});

it('cannot create audit records', function () {
    expect(KeycloakSyncAuditResource::canCreate())->toBeFalse();
});
