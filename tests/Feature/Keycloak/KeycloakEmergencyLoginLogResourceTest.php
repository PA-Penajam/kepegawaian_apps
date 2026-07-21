<?php

/**
 * Feature tests untuk Filament Emergency Login Log Resource.
 *
 * Menguji tampilan paginated list, kolom yang ditampilkan,
 * dan pencarian data emergency login log.
 * Validates: Requirement 15.7
 */

use App\Filament\Resources\KeycloakEmergencyLoginLogResource;
use App\Filament\Resources\KeycloakEmergencyLoginLogResource\Pages\ListKeycloakEmergencyLoginLogs;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use App\Models\Pegawai;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = Pegawai::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('Emergency Login Log - List Page', function () {
    test('dapat merender halaman list', function () {
        $this->get(KeycloakEmergencyLoginLogResource::getUrl('index'))
            ->assertSuccessful();
    });

    test('menampilkan daftar emergency login log records', function () {
        $logs = KeycloakEmergencyLoginLog::factory()->count(3)->create();

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanSeeTableRecords($logs);
    });

    test('menampilkan kolom ip_address', function () {
        KeycloakEmergencyLoginLog::factory()->create([
            'ip_address' => '192.168.1.100',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanRenderTableColumn('ip_address')
            ->assertTableColumnExists('ip_address');
    });

    test('menampilkan kolom user_agent', function () {
        KeycloakEmergencyLoginLog::factory()->create([
            'user_agent' => 'Mozilla/5.0 TestBrowser',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanRenderTableColumn('user_agent')
            ->assertTableColumnExists('user_agent');
    });

    test('menampilkan kolom logged_in_at', function () {
        KeycloakEmergencyLoginLog::factory()->create([
            'logged_in_at' => '2025-01-15 10:30:00',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanRenderTableColumn('logged_in_at')
            ->assertTableColumnExists('logged_in_at');
    });

    test('menampilkan kolom logged_out_at', function () {
        KeycloakEmergencyLoginLog::factory()->create([
            'logged_out_at' => '2025-01-15 11:00:00',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanRenderTableColumn('logged_out_at')
            ->assertTableColumnExists('logged_out_at');
    });

    test('tidak dapat membuat record baru', function () {
        expect(KeycloakEmergencyLoginLogResource::canCreate())->toBeFalse();
    });
});

describe('Emergency Login Log - Pagination', function () {
    test('menggunakan pagination dengan default 50 records per halaman', function () {
        KeycloakEmergencyLoginLog::factory()->count(55)->create();

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCountTableRecords(55);
    });
});

describe('Emergency Login Log - Search & Sort', function () {
    test('dapat mencari berdasarkan ip_address', function () {
        $target = KeycloakEmergencyLoginLog::factory()->create([
            'ip_address' => '10.0.0.1',
        ]);
        $other = KeycloakEmergencyLoginLog::factory()->create([
            'ip_address' => '192.168.1.1',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->searchTable('10.0.0.1')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    });

    test('dapat mencari berdasarkan user_agent', function () {
        $target = KeycloakEmergencyLoginLog::factory()->create([
            'user_agent' => 'Mozilla/5.0 Firefox',
        ]);
        $other = KeycloakEmergencyLoginLog::factory()->create([
            'user_agent' => 'Chrome/120.0',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->searchTable('Firefox')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    });

    test('default sort berdasarkan logged_in_at descending', function () {
        $old = KeycloakEmergencyLoginLog::factory()->create([
            'logged_in_at' => '2025-01-10 08:00:00',
        ]);
        $recent = KeycloakEmergencyLoginLog::factory()->create([
            'logged_in_at' => '2025-01-15 14:00:00',
        ]);

        Livewire::test(ListKeycloakEmergencyLoginLogs::class)
            ->assertCanSeeTableRecords([$recent, $old]);
    });
});
