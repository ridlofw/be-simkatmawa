<?php

namespace App\Enums;

/**
 * Jenis Rekognisi sesuai standar Kemdikbud.
 */
enum JenisRekognisi: string
{
    case SERKOM = 'SERKOM';     // Sertifikat Kompetensi
    case JURIOR = 'JURIOR';     // Juri/Pelatih/Wasit Olahraga
    case JURINOR = 'JURINOR';   // Juri/Pelatih/Wasit Non Olahraga
    case KEYCONF = 'KEYCONF';   // Keynote speaker conference
    case KEYWORK = 'KEYWORK';   // Keynote speaker workshop/pelatihan/bimbingan teknis
    case PAMERAN = 'PAMERAN';   // Pameran karya seni
    case KARYA = 'KARYA';       // Karya cipta lagu dan/atau seni tari
    case BUKU = 'BUKU';         // Penulis buku
    case PATEN = 'PATEN';       // Paten/Paten Sederhana
    case PUB = 'PUB';           // Publikasi artikel ilmiah
    case DUTA = 'DUTA';         // Duta (Brand Ambassador)
    case PTG = 'PTG';           // Produk Teknologi tepat guna
    case PSB = 'PSB';           // Produk Seni dan Budaya
    case PKD = 'PKD';           // Produk Kreatif Dunia Usaha dan Industri
}
