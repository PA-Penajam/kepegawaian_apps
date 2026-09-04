<?php

namespace Database\Factories;

use App\Models\SyncConsumer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SyncConsumer>
 */
class SyncConsumerFactory extends Factory
{
    protected $model = SyncConsumer::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug();

        return [
            'nama' => $this->faker->company(),
            'slug' => Str::limit($slug, 50, ''),
            'base_url' => 'https://'.$slug.'.test',
            'deskripsi' => 'Konsumen sinkronisasi data pegawai.',
            'is_active' => true,
        ];
    }
}
