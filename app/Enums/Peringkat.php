<?php

namespace App\Enums;

/**
 * Peringkat/Juara sesuai standar Kemdikbud.
 */
enum Peringkat: string
{
    case JUARA1 = 'JUARA1';
    case JUARA2 = 'JUARA2';
    case JUARA3 = 'JUARA3';
    case HARAPAN1 = 'HARAPAN1';
    case HARAPAN2 = 'HARAPAN2';
    case HARAPAN3 = 'HARAPAN3';
    case APRESIASI = 'APRESIASI';  // Apresiasi Kejuaraan / Penghargaan Tambahan
    case PESERTA = 'PESERTA';
}
