<?php

use App\Http\Requests\Kepegawaian\UpdatePenghargaanRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatJabatanRequest;
use App\Http\Requests\Kepegawaian\UpdateRiwayatPangkatRequest;
use App\Models\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->viewerUser = Pegawai::factory()->viewer()->create();
    $this->adminUser = Pegawai::factory()->admin()->create();
    $this->operatorUser = Pegawai::factory()->operator()->create();
});

test('viewer user cannot authorize UpdatePenghargaanRequest', function () {
    $request = new UpdatePenghargaanRequest;
    $request->setUserResolver(fn () => $this->viewerUser);

    expect($request->authorize())->toBeFalse();
});

test('admin user can authorize UpdatePenghargaanRequest', function () {
    $request = new UpdatePenghargaanRequest;
    $request->setUserResolver(fn () => $this->adminUser);

    expect($request->authorize())->toBeTrue();
});

test('operator user can authorize UpdatePenghargaanRequest', function () {
    $request = new UpdatePenghargaanRequest;
    $request->setUserResolver(fn () => $this->operatorUser);

    expect($request->authorize())->toBeTrue();
});

test('viewer user cannot authorize UpdateRiwayatJabatanRequest', function () {
    $request = new UpdateRiwayatJabatanRequest;
    $request->setUserResolver(fn () => $this->viewerUser);

    expect($request->authorize())->toBeFalse();
});

test('admin user can authorize UpdateRiwayatJabatanRequest', function () {
    $request = new UpdateRiwayatJabatanRequest;
    $request->setUserResolver(fn () => $this->adminUser);

    expect($request->authorize())->toBeTrue();
});

test('operator user can authorize UpdateRiwayatJabatanRequest', function () {
    $request = new UpdateRiwayatJabatanRequest;
    $request->setUserResolver(fn () => $this->operatorUser);

    expect($request->authorize())->toBeTrue();
});

test('viewer user cannot authorize UpdateRiwayatPangkatRequest', function () {
    $request = new UpdateRiwayatPangkatRequest;
    $request->setUserResolver(fn () => $this->viewerUser);

    expect($request->authorize())->toBeFalse();
});

test('admin user can authorize UpdateRiwayatPangkatRequest', function () {
    $request = new UpdateRiwayatPangkatRequest;
    $request->setUserResolver(fn () => $this->adminUser);

    expect($request->authorize())->toBeTrue();
});

test('operator user can authorize UpdateRiwayatPangkatRequest', function () {
    $request = new UpdateRiwayatPangkatRequest;
    $request->setUserResolver(fn () => $this->operatorUser);

    expect($request->authorize())->toBeTrue();
});
