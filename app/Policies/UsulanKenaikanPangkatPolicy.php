<?php

namespace App\Policies;

use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DitandatanganiKetuaState;
use App\States\UsulanKenaikanPangkat\DiverifikasiKasubbagState;
use App\States\UsulanKenaikanPangkat\DiverifikasiSekretarisState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\MenungguSkState;

class UsulanKenaikanPangkatPolicy
{
    /**
     * Melihat daftar semua usulan.
     */
    public function viewAny(Pegawai $user): bool
    {
        return $user->hasPermission('kenaikan-pangkat.usulan.view');
    }

    /**
     * Melihat detail usulan. Self (pegawai_id) atau punya permission.
     */
    public function view(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->pegawai_id === $user->id
            || $user->hasPermission('kenaikan-pangkat.usulan.view');
    }

    /**
     * Membuat usulan baru.
     */
    public function create(Pegawai $user): bool
    {
        return $user->hasPermission('kenaikan-pangkat.usulan.create');
    }

    /**
     * Update usulan hanya saat Draft/PerluPerbaikan dan self atau permission.
     */
    public function update(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowedStates = [DraftState::class, 'PerluPerbaikanState'];

        return in_array($usulan->state::class, $allowedStates)
            && ($usulan->pegawai_id === $user->id || $user->hasPermission('kenaikan-pangkat.usulan.update'));
    }

    /**
     * Hapus hanya Draft dan self.
     */
    public function delete(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof DraftState && $usulan->pegawai_id === $user->id;
    }

    /**
     * Submit usulan dari Draft → Diajukan.
     */
    public function submit(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowed = [DraftState::class, 'PerluPerbaikanState'];

        return in_array($usulan->state::class, $allowed)
            && $user->hasPermission('kenaikan-pangkat.usulan.submit');
    }

    /**
     * Verifikasi KasubbagHukum hanya saat state Diajukan.
     */
    public function verifikasiKasubbag(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof DiajukanState
            && $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-kasubbag');
    }

    /**
     * Verifikasi Sekretaris hanya saat DiverifikasiKasubbag.
     */
    public function verifikasiSekretaris(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof DiverifikasiKasubbagState
            && $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-sekretaris');
    }

    /**
     * Tanda tangan Ketua hanya saat DiverifikasiSekretaris.
     */
    public function tandaTanganKetua(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof DiverifikasiSekretarisState
            && $user->hasPermission('kenaikan-pangkat.usulan.tanda-tangan-ketua');
    }

    /**
     * Kirim ke Biro hanya saat DitandatanganiKetua.
     */
    public function kirimBiro(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof DitandatanganiKetuaState
            && $user->hasPermission('kenaikan-pangkat.usulan.kirim-biro');
    }

    /**
     * Upload SK Final hanya saat MenungguSK.
     */
    public function uploadSk(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        return $usulan->state instanceof MenungguSkState
            && $user->hasPermission('kenaikan-pangkat.usulan.upload-sk');
    }

    /**
     * Batalkan usulan hanya saat Draft/PerluPerbaikan dan self owner.
     */
    public function batalkan(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowed = [DraftState::class, 'PerluPerbaikanState'];

        return in_array($usulan->state::class, $allowed) && $usulan->pegawai_id === $user->id;
    }
}
