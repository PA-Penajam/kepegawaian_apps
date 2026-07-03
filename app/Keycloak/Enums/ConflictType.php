<?php

namespace App\Keycloak\Enums;

/**
 * Enum untuk jenis konflik antara data Pegawai dan Keycloak user.
 *
 * Digunakan oleh ConflictResolver untuk mengklasifikasikan
 * perbedaan data yang terdeteksi saat sinkronisasi.
 */
enum ConflictType: string
{
    /** Email, firstName, atau lastName berbeda */
    case DataMismatch = 'data_mismatch';

    /** Status aktif Pegawai berbeda dengan enabled flag Keycloak */
    case StatusConflict = 'status_conflict';

    /** Role mappings Pegawai berbeda dengan realm roles Keycloak */
    case RoleOverride = 'role_override';

    /** NIP atau email identifier berubah */
    case IdentifierChange = 'identifier_change';
}
