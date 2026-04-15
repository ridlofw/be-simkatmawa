<?php

namespace App\Enums;

/**
 * Level kegiatan sesuai standar Kemdikbud Simkatmawa.
 */
enum Level: string
{
    case KAB = 'KAB';   // Kabupaten
    case PROV = 'PROV';  // Provinsi
    case NAS = 'NAS';    // Nasional
    case INT = 'INT';    // Internasional
}
