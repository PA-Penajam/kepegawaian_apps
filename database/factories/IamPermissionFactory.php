<?php

namespace Database\Factories;

use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IamPermission>
 */
class IamPermissionFactory extends Factory
{
    protected $model = IamPermission::class;

    public function definition(): array
    {
        // Default canonical slug (2-segment)
        $resource = $this->faker->unique()->word();
        $action = $this->faker->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'iam_application_id' => IamApplication::factory(),
            'nama' => ucfirst($action).' '.ucfirst($resource),
            'slug' => "{$resource}.{$action}",
            'group' => $resource,
            'keterangan' => null,
        ];
    }

    /** State untuk slug legacy (non-canonical) — pakai untuk test audit */
    public function legacy(string $slug = 'iam-manage'): static
    {
        return $this->state(fn () => [
            'slug' => $slug,
            'group' => str_contains($slug, '.') ? explode('.', $slug)[0] : $slug,
        ]);
    }
}
