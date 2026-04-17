<?php

namespace Database\Factories;

use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IamRole>
 */
class IamRoleFactory extends Factory
{
    protected $model = IamRole::class;

    public function definition(): array
    {
        return [
            'iam_application_id' => IamApplication::factory(),
            'nama' => fake()->jobTitle(),
            'slug' => fake()->unique()->slug(2),
            'keterangan' => fake()->optional()->sentence(),
            'is_system' => false,
        ];
    }
}
