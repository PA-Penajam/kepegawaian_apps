<?php

namespace Database\Factories\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasChecklistItem>
 */
class BerkasChecklistItemFactory extends Factory
{
    protected $model = BerkasChecklistItem::class;

    public function definition(): array
    {
        return [
            'berkas_checklist_template_id' => BerkasChecklistTemplate::factory(),
            'kode' => fake()->unique()->slug(2),
            'nama' => fake()->words(3, true),
            'deskripsi' => fake()->optional()->sentence(),
            'wajib' => true,
            'urutan' => fake()->numberBetween(1, 100),
        ];
    }
}
