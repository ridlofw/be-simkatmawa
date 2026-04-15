<?php

namespace App\Enums;

/**
 * Role pengguna sistem internal Simkatmawa Udinus.
 */
enum UserRole: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case MAHASISWA = 'mahasiswa';
}
