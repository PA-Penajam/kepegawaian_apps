<?php

namespace Database\Factories\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasChecklistTemplate>
 */
class BerkasChecklistTemplateFactory extends Factory
{
    protected $model = BerkasChecklistTemplate::class;

    public function definition(): array
    {
        return [
            'jenis' => fake()->randomElement(['kenaikan_pangkat', 'cuti', 'pegawai']),
            'kode' => fake()->unique()->slug(3),
            'nama' => fake()->words(3, true),
            'deskripsi' => fake()->optional()->sentence(),
            'aktif' => true,
            'urutan' => fake()->numberBetween(1, 100),
        ];
    }
}
