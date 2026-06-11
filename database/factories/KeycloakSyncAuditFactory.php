<?php

namespace Database\Factories;

use App\Keycloak\Models\KeycloakSyncAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model KeycloakSyncAudit.
 *
 * @extends Factory<KeycloakSyncAudit>
 */
class KeycloakSyncAuditFactory extends Factory
{
    protected $model = KeycloakSyncAudit::class;

    public function definition(): array
    {
        $eventType = fake()->randomElement(['create', 'update', 'conflict', 'sync_failure']);

        return [
            'event_type' => $eventType,
            'pegawai_id' => null,
            'nip' => fake()->numerify('##################'),
            'conflict_type' => $eventType === 'conflict' ? fake()->randomElement(['data_mismatch', 'status_conflict', 'role_override', 'identifier_change']) : null,
            'pegawai_snapshot' => ['nip' => fake()->numerify('##################'), 'nama' => fake()->name()],
            'keycloak_snapshot' => $eventType === 'conflict' ? ['username' => fake()->numerify('##################'), 'email' => fake()->safeEmail()] : null,
            'resolution' => $eventType === 'conflict' ? ['action' => 'pegawai_wins', 'fields_updated' => ['email']] : null,
            'resolved_by' => 'system',
            'caused_by' => null,
            'caused_by_nip' => null,
        ];
    }

    /**
     * State untuk event tipe create.
     */
    public function eventCreate(): static
    {
        return $this->state(fn () => [
            'event_type' => 'create',
            'conflict_type' => null,
            'keycloak_snapshot' => null,
            'resolution' => null,
        ]);
    }

    /**
     * State untuk event tipe conflict.
     */
    public function eventConflict(): static
    {
        return $this->state(fn () => [
            'event_type' => 'conflict',
            'conflict_type' => fake()->randomElement(['data_mismatch', 'status_conflict', 'role_override', 'identifier_change']),
            'keycloak_snapshot' => ['username' => fake()->numerify('##################'), 'email' => fake()->safeEmail()],
            'resolution' => ['action' => 'pegawai_wins', 'fields_updated' => ['email']],
        ]);
    }

    /**
     * State untuk event tipe sync_failure.
     */
    public function eventSyncFailure(): static
    {
        return $this->state(fn () => [
            'event_type' => 'sync_failure',
            'conflict_type' => null,
            'keycloak_snapshot' => null,
            'resolution' => null,
        ]);
    }
}
