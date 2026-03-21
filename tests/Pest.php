<?php

require_once __DIR__ . '/Helpers/IamTestHelper.php';

use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Seed IAM data agar middleware dan factory berfungsi
        $this->seed(IamSeeder::class);
    })
    ->in('Feature');
