<?php

use App\Models\IamApplication;
use App\Models\IamUserRole;
use App\Models\User;

beforeEach(function () {
    $cred = IamApplication::generateApiCredentials();
    $this->kepegawaian = IamApplication::create([
        'nama' => 'Kepegawaian', 'slug' => 'kepegawaian', 'url' => 'http://test.local',
        'api_key' => $cred['key'], 'api_secret_hash' => $cred['hash'], 'is_active' => true,
    ]);
    $this->adminRole = $this->kepegawaian->roles()->create(['nama' => 'Admin', 'slug' => 'admin', 'is_system' => false]);
    // Berikan permission ke role admin agar bisa melewati middleware VerifyIamPermission
    $perm = $this->kepegawaian->permissions()->create(['nama' => 'Manage IAM', 'slug' => 'iam-manage']);
    $this->adminRole->permissions()->attach($perm->id);

    $this->admin = User::factory()->create();
    IamUserRole::create(['user_id' => $this->admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);
});

it('menolak update permission milik aplikasi lain (IDOR)', function () {
    $cred2 = IamApplication::generateApiCredentials();
    $app2  = IamApplication::create([
        'nama' => 'App 2', 'slug' => 'app-2', 'url' => 'https://app2.test',
        'api_key' => $cred2['key'], 'api_secret_hash' => $cred2['hash'], 'is_active' => true,
    ]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Read', 'slug' => 'read']);

    $response = $this->actingAs($this->admin)
        ->put("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}", [
            'nama' => 'Hacked', 'slug' => 'hacked',
        ]);

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id, 'nama' => 'Read']);
});

it('menolak hapus permission milik aplikasi lain (IDOR)', function () {
    $cred2 = IamApplication::generateApiCredentials();
    $app2  = IamApplication::create([
        'nama' => 'App 2', 'slug' => 'app-2', 'url' => 'https://app2.test',
        'api_key' => $cred2['key'], 'api_secret_hash' => $cred2['hash'], 'is_active' => true,
    ]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Delete', 'slug' => 'delete']);

    $response = $this->actingAs($this->admin)
        ->delete("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id]);
});

it('menolak slug permission duplikat dalam satu aplikasi', function () {
    $this->kepegawaian->permissions()->create(['nama' => 'Read', 'slug' => 'read']);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Read Duplicate', 'slug' => 'read',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');
});
