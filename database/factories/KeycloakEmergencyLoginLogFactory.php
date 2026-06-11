<?php

namespace Database\Factories;

use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model KeycloakEmergencyLoginLog.
 *
 * @extends Factory<KeycloakEmergencyLoginLog>
 */
class KeycloakEmergencyLoginLogFactory extends Factory
{
    protected $model = KeycloakEmergencyLoginLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'username' => hash('sha256', fake()->userName()),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'logged_in_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'logged_out_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'outcome' => fake()->randomElement(['success', 'failure']),
        ];
    }

    /**
     * State untuk login yang masih aktif (belum logout).
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'logged_out_at' => null,
            'outcome' => 'success',
        ]);
    }

    /**
     * State untuk login yang sudah logout.
     */
    public function loggedOut(): static
    {
        return $this->state(fn () => [
            'logged_out_at' => now(),
            'outcome' => 'success',
        ]);
    }

    /**
     * State untuk percobaan login yang gagal.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'logged_out_at' => null,
            'outcome' => 'failure',
        ]);
    }
}
