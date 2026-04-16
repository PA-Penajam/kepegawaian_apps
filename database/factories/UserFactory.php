<?php

namespace Database\Factories;

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Assign admin IAM role to the user (kepegawaian app).
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = $this->resolveKepegawaianRole('admin');
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $user->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    /**
     * Assign operator IAM role to the user (kepegawaian app).
     */
    public function operator(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = $this->resolveKepegawaianRole('operator');
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $user->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    /**
     * Assign viewer IAM role to the user (kepegawaian app).
     */
    public function viewer(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = $this->resolveKepegawaianRole('viewer');
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $user->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    /**
     * Pastikan IamApplication kepegawaian dan role-nya ada, lalu kembalikan role yang diminta.
     * Berguna di test dengan RefreshDatabase agar factory tidak gagal diam-diam.
     */
    private function resolveKepegawaianRole(string $roleSlug): ?IamRole
    {
        ['key' => $key, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::firstOrCreate(
            ['slug' => 'kepegawaian'],
            [
                'nama'            => 'Kepegawaian',
                'url'             => 'http://localhost',
                'api_key'         => $key,
                'api_secret_hash' => $hash,
                'is_active'       => true,
                'is_system'       => true,
            ]
        );

        return IamRole::firstOrCreate(
            ['iam_application_id' => $app->id, 'slug' => $roleSlug],
            ['nama' => ucfirst($roleSlug), 'is_system' => false]
        );
    }
}
