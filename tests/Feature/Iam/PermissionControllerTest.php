<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\Pegawai;

beforeEach(function () {
    // Gunakan data dari IamSeeder
    $this->kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
    $this->adminRole = IamRole::where('iam_application_id', $this->kepegawaian->id)
        ->where('slug', 'admin')->first();

    $this->admin = Pegawai::factory()->admin()->create();
});

it('menolak update permission milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Read', 'slug' => 'read']);

    $response = $this->actingAs($this->admin)
        ->put("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}", [
            'nama' => 'Hacked', 'slug' => 'hacked',
        ]);

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id, 'nama' => 'Read']);
});

it('menolak hapus permission milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Delete', 'slug' => 'delete']);

    $response = $this->actingAs($this->admin)
        ->delete("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id]);
});

it('menolak slug permission duplikat dalam satu aplikasi', function () {
    // Slug 'pegawai.view' sudah ada dari IamSeeder
    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Duplicate', 'slug' => 'pegawai.view',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');
});
