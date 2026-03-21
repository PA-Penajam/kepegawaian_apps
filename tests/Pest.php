<?php

require_once __DIR__ . '/Helpers/IamTestHelper.php';

use Database\Seeders\IamSeeder;
use Database\Seeders\RefPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Seed IAM data dan permissions agar factory dan middleware berfungsi
        $this->seed(IamSeeder::class);
        $this->seed(RefPermissionSeeder::class);
    })
    ->in('Feature');
