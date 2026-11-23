<?php

namespace App\Policies;

use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DikirimBiroState;
use App\States\UsulanKenaikanPangkat\DitandatanganiKetuaState;
use App\States\UsulanKenaikanPangkat\DiverifikasiKasubbagState;
use App\States\UsulanKenaikanPangkat\DiverifikasiSekretarisState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\MenungguSkState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;

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
        $allowedStates = [DraftState::class, PerluPerbaikanState::class];

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
        $allowed = [DraftState::class, PerluPerbaikanState::class];

        return in_array($usulan->state::class, $allowed)
            && $user->hasPermission('kenaikan-pangkat.usulan.submit');
    }

    /**
     * Verifikasi KasubbagHukum hanya saat state Diajukan dan bukan pemohon usulan (anti self-approval).
     */
    public function verifikasiKasubbag(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return $usulan->state instanceof DiajukanState
            && $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-kasubbag');
    }

    /**
     * Verifikasi Sekretaris hanya saat DiverifikasiKasubbag dan bukan pemohon usulan (anti self-approval).
     */
    public function verifikasiSekretaris(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return $usulan->state instanceof DiverifikasiKasubbagState
            && $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-sekretaris');
    }

    /**
     * Tanda tangan Ketua hanya saat DiverifikasiSekretaris dan bukan pemohon usulan (anti self-approval).
     */
    public function tandaTanganKetua(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return $usulan->state instanceof DiverifikasiSekretarisState
            && $user->hasPermission('kenaikan-pangkat.usulan.tanda-tangan-ketua');
    }

    /**
     * Kirim ke Biro hanya saat DitandatanganiKetua dan bukan pemohon usulan (anti self-approval).
     */
    public function kirimBiro(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return $usulan->state instanceof DitandatanganiKetuaState
            && $user->hasPermission('kenaikan-pangkat.usulan.kirim-biro');
    }

    /**
     * Upload SK Final hanya saat MenungguSK dan bukan pemohon usulan (anti self-approval).
     */
    public function uploadSk(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return $usulan->state instanceof MenungguSkState
            && $user->hasPermission('kenaikan-pangkat.usulan.upload-sk');
    }

    /**
     * Meminta perbaikan usulan hanya oleh approver tahap aktif (anti self-approval).
     */
    public function mintaPerbaikan(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return match ($usulan->state::class) {
            DiajukanState::class => $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-kasubbag'),
            DiverifikasiKasubbagState::class => $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-sekretaris'),
            DiverifikasiSekretarisState::class => $user->hasPermission('kenaikan-pangkat.usulan.tanda-tangan-ketua'),
            DikirimBiroState::class => $user->hasPermission('kenaikan-pangkat.usulan.kirim-biro'),
            MenungguSkState::class => $user->hasAnyPermission('kenaikan-pangkat.usulan.kirim-biro', 'kenaikan-pangkat.usulan.upload-sk'),
            default => false,
        };
    }

    /**
     * Menolak usulan hanya oleh approver tahap aktif yang mengizinkan penolakan (anti self-approval).
     */
    public function tolak(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        return match ($usulan->state::class) {
            DiverifikasiKasubbagState::class => $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-sekretaris'),
            DiverifikasiSekretarisState::class => $user->hasPermission('kenaikan-pangkat.usulan.tanda-tangan-ketua'),
            MenungguSkState::class => $user->hasAnyPermission('kenaikan-pangkat.usulan.kirim-biro', 'kenaikan-pangkat.usulan.upload-sk'),
            default => false,
        };
    }

    /**
     * Unggah berkas item checklist saat Draft/PerluPerbaikan oleh pemilik atau pengguna berizin.
     */
    public function uploadChecklist(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowed = [DraftState::class, PerluPerbaikanState::class];

        return in_array($usulan->state::class, $allowed, true)
            && ($usulan->pegawai_id === $user->id
                || $user->hasAnyPermission('kenaikan-pangkat.usulan.update', 'checklist.submission.update-item'));
    }

    /**
     * Validasi item checklist hanya oleh validator berizin dan bukan pemohon usulan.
     */
    public function validateChecklist(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        if ($usulan->pegawai_id === $user->id) {
            return false;
        }

        $allowed = [
            DraftState::class,
            PerluPerbaikanState::class,
            DiajukanState::class,
            DiverifikasiKasubbagState::class,
            DiverifikasiSekretarisState::class,
        ];

        return in_array($usulan->state::class, $allowed, true)
            && $user->hasPermission('checklist.submission.validate-item');
    }

    /**
     * Unggah atau hapus lampiran tambahan saat Draft/PerluPerbaikan oleh pemilik atau pengguna berizin.
     */
    public function uploadLampiran(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowed = [DraftState::class, PerluPerbaikanState::class];

        return in_array($usulan->state::class, $allowed, true)
            && ($usulan->pegawai_id === $user->id
                || $user->hasPermission('kenaikan-pangkat.usulan.update'));
    }

    /**
     * Batalkan usulan hanya saat Draft/Diajukan/PerluPerbaikan dan self owner.
     */
    public function batalkan(Pegawai $user, UsulanKenaikanPangkat $usulan): bool
    {
        $allowed = [DraftState::class, DiajukanState::class, PerluPerbaikanState::class];

        return in_array($usulan->state::class, $allowed, true) && $usulan->pegawai_id === $user->id;
    }
}
