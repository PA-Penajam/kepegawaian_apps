<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

test('IamApplication memiliki relasi roles dan permissions', function () {
    $app = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test-app',
        'url'             => 'http://test.local',
        'api_key'         => 'test-key-123',
        'api_secret_hash' => bcrypt('secret'),
    ]);

    expect($app->roles())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($app->permissions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('IamRole memiliki relasi permissions dan application', function () {
    $app = IamApplication::factory()->create();
    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'nama'               => 'Admin',
        'slug'               => 'admin',
    ]);

    expect($role->application())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($role->permissions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

test('User memiliki relasi iamRoles', function () {
    $user = User::factory()->create();
    expect($user->iamRoles())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('IamApplication generateApiCredentials menghasilkan key dan secret', function () {
    ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

    expect($key)->toStartWith('iam_');
    expect(strlen($secret))->toBe(64);
    // api_secret_hash menggunakan Crypt::encryptString agar bisa di-retrieve untuk HMAC
    expect(Crypt::decryptString($hash))->toBe($secret);
});

test('IamApplication verifySecret memvalidasi secret dengan benar', function () {
    $plainSecret = 'correct-secret-value-64chars-padding-here-123456789-abc';
    $app = IamApplication::create([
        'nama'            => 'Test',
        'slug'            => 'test-verify',
        'url'             => 'http://test.local',
        'api_key'         => 'test-key-verify',
        'api_secret_hash' => Crypt::encryptString($plainSecret),
    ]);

    expect($app->verifySecret($plainSecret))->toBeTrue();
    expect($app->verifySecret('wrong-secret'))->toBeFalse();
});
