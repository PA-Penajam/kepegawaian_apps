<?php

namespace App\States\UsulanKenaikanPangkat;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class UsulanKenaikanPangkatState extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, DiajukanState::class)
            ->allowTransition(DraftState::class, DibatalkanState::class)
            ->allowTransition(DiajukanState::class, DiverifikasiKasubbagState::class)
            ->allowTransition(DiajukanState::class, PerluPerbaikanState::class)
            ->allowTransition(DiajukanState::class, DibatalkanState::class)
            ->allowTransition(DiverifikasiKasubbagState::class, DiverifikasiSekretarisState::class)
            ->allowTransition(DiverifikasiKasubbagState::class, PerluPerbaikanState::class)
            ->allowTransition(DiverifikasiKasubbagState::class, DitolakState::class)
            ->allowTransition(DiverifikasiSekretarisState::class, DitandatanganiKetuaState::class)
            ->allowTransition(DiverifikasiSekretarisState::class, PerluPerbaikanState::class)
            ->allowTransition(DiverifikasiSekretarisState::class, DitolakState::class)
            ->allowTransition(DitandatanganiKetuaState::class, DikirimBiroState::class)
            ->allowTransition(DikirimBiroState::class, MenungguSkState::class)
            ->allowTransition(DikirimBiroState::class, PerluPerbaikanState::class)
            ->allowTransition(MenungguSkState::class, SelesaiSkTerbitState::class)
            ->allowTransition(MenungguSkState::class, PerluPerbaikanState::class)
            ->allowTransition(MenungguSkState::class, DitolakState::class)
            ->allowTransition(PerluPerbaikanState::class, DraftState::class)
            ->allowTransition(PerluPerbaikanState::class, DibatalkanState::class);
    }
}
