<?php

namespace App\Keycloak\Enums;

/**
 * Enum untuk kebijakan resolusi konflik data.
 *
 * Saat ini hanya mendukung kebijakan PegawaiWins dimana
 * data Pegawai selalu menjadi source of truth.
 */
enum ConflictPolicy: string
{
    /** Data Pegawai selalu menang (source of truth) */
    case PegawaiWins = 'pegawai_wins';
}
