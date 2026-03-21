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

    public function definition(): array
    {
        $secret = Str::random(64);

        return [
            'nama'            => fake()->company(),
            'slug'            => fake()->unique()->slug(2),
            'url'             => fake()->url(),
            'deskripsi'       => fake()->sentence(),
            'api_key'         => 'iam_' . Str::random(32),
            'api_secret_hash' => encrypt($secret),
            'is_active'       => true,
            'is_system'       => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
