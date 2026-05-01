<?php

namespace App\States\Cuti;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PengajuanState extends State
{
    /**
     * Nama internal state (untuk database).
     */
    abstract public function name(): string;

    /**
     * Label tampilan state.
     */
    abstract public function label(): string;

    /**
     * Apakah state ini terminal (tidak bisa transition lagi).
     */
    public function isTerminal(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, DiajukanState::class)
            ->allowTransition(DraftState::class, DibatalkanState::class)
            ->allowTransition(DiajukanState::class, DiverifikasiState::class)
            ->allowTransition(DiajukanState::class, DitolakKepegawaianState::class)
            ->allowTransition(DiverifikasiState::class, DisetujuiAtasanState::class)
            ->allowTransition(DiverifikasiState::class, DitolakAtasanState::class)
            ->allowTransition(DisetujuiAtasanState::class, DisetujuiState::class)
            ->allowTransition(DisetujuiAtasanState::class, DitolakPejabatState::class)
            ->allowTransition(DisetujuiState::class, DicabutSetelahDisetujuiState::class);
    }
}
