<?php

// tests/Feature/Iam/IamTablesTest.php

use Illuminate\Support\Facades\Schema;

test('tabel iam_applications ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_applications'))->toBeTrue();
    expect(Schema::hasColumns('iam_applications', [
        'id', 'nama', 'slug', 'url', 'deskripsi',
        'api_key', 'api_secret_hash', 'is_active', 'is_system',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_roles ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_roles'))->toBeTrue();
    expect(Schema::hasColumns('iam_roles', [
        'id', 'iam_application_id', 'nama', 'slug',
        'keterangan', 'is_system', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_permissions ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_permissions'))->toBeTrue();
    expect(Schema::hasColumns('iam_permissions', [
        'id', 'iam_application_id', 'nama', 'slug',
        'group', 'keterangan', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_role_permissions ada', function () {
    expect(Schema::hasTable('iam_role_permissions'))->toBeTrue();
    expect(Schema::hasColumns('iam_role_permissions', [
        'id', 'iam_role_id', 'iam_permission_id',
    ]))->toBeTrue();
});

test('tabel iam_user_roles ada', function () {
    expect(Schema::hasTable('iam_user_roles'))->toBeTrue();
    expect(Schema::hasColumns('iam_user_roles', [
        'id', 'user_id', 'iam_role_id', 'assigned_at', 'assigned_by',
    ]))->toBeTrue();
});

test('tabel iam_sso_codes ada', function () {
    expect(Schema::hasTable('iam_sso_codes'))->toBeTrue();
    expect(Schema::hasColumns('iam_sso_codes', [
        'id', 'code', 'user_id', 'app_slug', 'used_at', 'expires_at',
    ]))->toBeTrue();
});
