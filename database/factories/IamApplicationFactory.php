<?php

namespace Database\Factories;

use App\Models\IamApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IamApplication>
 */
class IamApplicationFactory extends Factory
{
    protected $model = IamApplication::class;

    /**
     * Callback otomatis untuk set api_key, api_secret_hash, dan is_system.
     * Diperlukan karena field ini tidak mass-assignable (security).
     * Factory mengisi nilai default agar test/seeders berjalan lancar.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (IamApplication $app) {
            // Set api_key dan secret jika belum ada
            if (empty($app->api_key)) {
                $secret = Str::random(64);
                $app->api_key = 'iam_'.Str::random(32);
                $app->api_secret_hash = encrypt($secret);
            }

            // Default is_system = false jika belum set
            if (!isset($app->is_system)) {
                $app->is_system = false;
            }
        });
    }

    public function definition(): array
    {
        return [
            'nama' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'url' => fake()->url(),
            'deskripsi' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Buat aplikasi sistem (is_system = true).
     */
    public function system(): static
    {
        return $this->state(fn () => [
            'is_system' => true,
        ]);
    }

    /**
     * Buat aplikasi tidak aktif (is_active = false).
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
