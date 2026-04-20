<?php

namespace Tests\Feature\Iam;

use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IamSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_iam_seeder_can_run_multiple_times_without_error(): void
    {
        $this->seed(IamSeeder::class);
        $this->seed(IamSeeder::class);

        $this->assertDatabaseCount('iam_applications', 1);
    }
}
