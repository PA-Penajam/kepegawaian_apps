<?php

use Database\Seeders\RefPermissionSeeder;
use Database\Seeders\RefRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Seed roles & permissions agar factory ->admin(), ->operator(), ->viewer() berfungsi
        $this->seed(RefRoleSeeder::class);
        $this->seed(RefPermissionSeeder::class);
    })
    ->in('Feature');
