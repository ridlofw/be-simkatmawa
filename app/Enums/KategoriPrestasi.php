<?php

namespace App\Enums;

/**
 * Kategori Prestasi Mandiri sesuai standar Kemdikbud.
 */
enum KategoriPrestasi: string
{
    case RISNOV = 'RISNOV';         // Riset dan Inovasi STEM
    case RISNOVSSH = 'RISNOVSSH';   // Riset dan Inovasi SSH
    case SENBUD = 'SENBUD';         // Seni dan Budaya
    case OLAHRAGA = 'OLAHRAGA';     // Olahraga
    case MINAT = 'MINAT';           // Minat Khusus
}
