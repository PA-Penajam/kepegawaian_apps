<?php

namespace App\Enums;

enum StatusSuratDinas: string
{
    case BelumTerkirim = 'BELUM_TERKIRIM';
    case MenungguNomor = 'MENUNGGU_NOMOR';
    case PerluPerbaikan = 'PERLU_PERBAIKAN';
    case NomorTerbit = 'NOMOR_TERBIT';
    case FinalTersedia = 'FINAL_TERSEDIA';
    case Terkirim = 'TERKIRIM';
    case Dibatalkan = 'DIBATALKAN';

    /**
     * Label berbahasa Indonesia untuk tampilan UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::BelumTerkirim => 'Belum Terkirim ke SiSukma',
            self::MenungguNomor => 'Menunggu Verifikasi & Nomor SiSukma',
            self::PerluPerbaikan => 'Perlu Perbaikan',
            self::NomorTerbit => 'Nomor Terbit (Menunggu Tanda Tangan & Scan Final)',
            self::FinalTersedia => 'Surat Final Tersedia di SiSukma',
            self::Terkirim => 'Terkirim / Selesai Ekspedisi',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Menentukan apakah surat masih boleh dibatalkan melalui API sebelum nomor resmi terbit.
     */
    public function canCancelBeforeNumbered(): bool
    {
        return in_array($this, [
            self::BelumTerkirim,
            self::MenungguNomor,
            self::PerluPerbaikan,
        ], true);
    }

    /**
     * Menentukan apakah berkas surat final sudah tersedia untuk diunduh dari SiSukma.
     */
    public function hasFinalFile(): bool
    {
        return in_array($this, [self::FinalTersedia, self::Terkirim], true);
    }
}
