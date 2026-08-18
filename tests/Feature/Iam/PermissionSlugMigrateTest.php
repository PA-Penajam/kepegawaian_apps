<?php

use App\Models\IamApplication;
use App\Models\Pegawai;

beforeEach(function () {
    $this->kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
    $this->admin = Pegawai::factory()->admin()->create();
});

it('migrate slug demo-action menjadi demo.action', function () {
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'demo-action', 'group' => 'demo-action',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    expect($perm->fresh())
        ->slug->toBe('demo.action')
        ->group->toBe('demo');
});

it('tolak migrate jika tidak ada saran canonical', function () {
    // Slug dengan underscore tidak punya saran (suggestCanonical → null)
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Bad', 'slug' => 'foo_bar', 'group' => 'foo_bar',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');

    expect($perm->fresh()->slug)->toBe('foo_bar');
});

it('tolak migrate jika ada konflik unique', function () {
    // Sudah ada 'demo.action' canonical
    $this->kepegawaian->permissions()->create([
        'nama' => 'Existing', 'slug' => 'demo.action', 'group' => 'demo',
    ]);
    $legacy = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'demo-action', 'group' => 'demo-action',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$legacy->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');

    expect($legacy->fresh()->slug)->toBe('demo-action');
});

it('tolak migrate permission milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $permApp2 = $app2->permissions()->create([
        'nama' => 'Test', 'slug' => 'foo-bar', 'group' => 'foo-bar',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}/migrate-slug");

    $response->assertStatus(404);
});

it('mencatat audit log saat slug dimigrasi', function () {
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'audit-test', 'group' => 'audit-test',
    ]);

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'iam.permission',
        'subject_id' => $perm->id,
        'description' => 'slug-migrated',
    ]);
});
