<?php

use App\Models\Pegawai;
use App\Notifications\KenaikanPangkatEligibleNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

it('kolom notifiable_id pada tabel notifications mendukung ULID non-integer', function (): void {
    $columns = collect(Schema::getColumns('notifications'))->keyBy('name');

    expect($columns->has('notifiable_id'))->toBeTrue();

    $notifiableId = $columns->get('notifiable_id');
    expect(strtolower((string) ($notifiableId['type_name'] ?? '')))->not->toContain('int')
        ->and(strtolower((string) ($notifiableId['type'] ?? '')))->not->toContain('int');
});

it('tabel notifications memiliki indeks komposit notifiable_type dan notifiable_id', function (): void {
    $indexes = collect(Schema::getIndexes('notifications'))->keyBy('name');

    expect($indexes->has('notifications_notifiable_type_notifiable_id_index'))->toBeTrue()
        ->and($indexes->get('notifications_notifiable_type_notifiable_id_index')['columns'])
        ->toBe(['notifiable_type', 'notifiable_id']);
});

it('pegawai dengan primary key ULID dapat menerima dan membaca notifikasi database', function (): void {
    Mail::fake();

    $pegawai = Pegawai::factory()->create();
    expect(strlen($pegawai->id))->toBe(26);

    $pegawai->notify(new KenaikanPangkatEligibleNotification(4, 2026, '2026-03-15'));

    expect($pegawai->notifications()->count())->toBe(1)
        ->and($pegawai->notifications()->first()?->notifiable_id)->toBe($pegawai->id);

    $response = $this->actingAs($pegawai)->getJson('/notifications');
    $response->assertSuccessful()
        ->assertJsonPath('unread_count', 1);
});
