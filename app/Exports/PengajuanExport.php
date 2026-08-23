<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Export class untuk Daftar Pengajuan Admin (Prestasi / Rekognisi / Sertifikasi).
 *
 * Fitur:
 * - Kolom statis terpusat (mudah diubah urutannya)
 * - Kolom dinamis mahasiswa (Ketua + Anggota N) & dosen (flatten)
 * - AutoFilter header (dropdown sort/filter di Excel)
 * - Freeze header row
 * - Auto-width kolom
 */
class PengajuanExport implements FromArray, WithEvents, WithTitle
{
    private string $tipeKegiatan;
    private Collection $data;
    private int $maxMahasiswa = 1;
    private int $maxDosen = 1;
    private int $totalColumns = 0;

    public function __construct(string $tipeKegiatan, Collection $data)
    {
        $this->tipeKegiatan = $tipeKegiatan;
        $this->data = $data;

        // Hitung jumlah mahasiswa & dosen terbanyak dari seluruh data
        foreach ($data as $item) {
            $mhsCount = $item->mahasiswa ? $item->mahasiswa->count() : 0;
            $dosenCount = $item->dosen ? $item->dosen->count() : 0;
            $this->maxMahasiswa = max($this->maxMahasiswa, $mhsCount);
            $this->maxDosen = max($this->maxDosen, $dosenCount);
        }
    }

    /**
     * =====================================================================
     * KONFIGURASI KOLOM STATIS — UBAH URUTAN DI SINI
     * =====================================================================
     * Untuk mengubah urutan kolom, cukup pindahkan item di array ini.
     * Untuk menambah kolom baru, tambahkan item baru.
     * Untuk menghapus kolom, hapus item dari array.
     *
     * Format: ['key' => 'nama_field_model', 'header' => 'Label Header Excel']
     * Key khusus: 'creator_name', 'approver_name' → diambil dari relasi
     * =====================================================================
     */
    private function getStaticColumns(): array
    {
        return match ($this->tipeKegiatan) {
            'prestasi' => [
                ['key' => 'id',                    'header' => 'ID'],
                ['key' => 'kategori',              'header' => 'Kategori'],
                ['key' => 'level',                 'header' => 'Level'],
                ['key' => 'lomba',                 'header' => 'Nama Lomba'],
                ['key' => 'cabang',                'header' => 'Cabang'],
                ['key' => 'penyelenggara',         'header' => 'Penyelenggara'],
                ['key' => 'peringkat',             'header' => 'Peringkat'],
                ['key' => 'jumlah_unit_peserta',   'header' => 'Jumlah Unit Peserta'],
                ['key' => 'kelompok_prestasi',     'header' => 'Kelompok'],
                ['key' => 'bentuk',                'header' => 'Bentuk'],
                ['key' => 'tgl_sertifikat',        'header' => 'Tanggal Sertifikat'],
                ['key' => 'url_peserta',           'header' => 'URL Peserta'],
                ['key' => 'url_sertifikat',        'header' => 'URL Sertifikat'],
                ['key' => 'url_foto_upp',          'header' => 'URL Foto UPP'],
                ['key' => 'url_dokumen_undangan',  'header' => 'URL Undangan'],
                ['key' => 'keterangan',            'header' => 'Keterangan'],
                ['key' => 'status_internal',       'header' => 'Status'],
                ['key' => 'alasan_penolakan',      'header' => 'Alasan Penolakan'],
                ['key' => 'pusat_kemdikbud_id',    'header' => 'ID Kemdikti'],
                ['key' => 'creator_name',          'header' => 'Diajukan Oleh'],
                ['key' => 'approver_name',         'header' => 'Diverifikasi Oleh'],
                ['key' => 'created_at',            'header' => 'Tanggal Pengajuan'],
                ['key' => 'approved_at',           'header' => 'Tanggal Verifikasi'],
            ],
            'rekognisi' => [
                ['key' => 'id',                    'header' => 'ID'],
                ['key' => 'jenis',                 'header' => 'Jenis Rekognisi'],
                ['key' => 'level',                 'header' => 'Level'],
                ['key' => 'nama',                  'header' => 'Nama Rekognisi'],
                ['key' => 'penyelenggara',         'header' => 'Penyelenggara'],
                ['key' => 'tgl_sertifikat',        'header' => 'Tanggal Sertifikat'],
                ['key' => 'url_peserta',           'header' => 'URL Peserta'],
                ['key' => 'url_sertifikat',        'header' => 'URL Sertifikat'],
                ['key' => 'url_foto_upp',          'header' => 'URL Foto UPP'],
                ['key' => 'url_dokumen_undangan',  'header' => 'URL Undangan'],
                ['key' => 'keterangan',            'header' => 'Keterangan'],
                ['key' => 'status_internal',       'header' => 'Status'],
                ['key' => 'alasan_penolakan',      'header' => 'Alasan Penolakan'],
                ['key' => 'pusat_kemdikbud_id',    'header' => 'ID Kemdikti'],
                ['key' => 'creator_name',          'header' => 'Diajukan Oleh'],
                ['key' => 'approver_name',         'header' => 'Diverifikasi Oleh'],
                ['key' => 'created_at',            'header' => 'Tanggal Pengajuan'],
                ['key' => 'approved_at',           'header' => 'Tanggal Verifikasi'],
            ],
            'sertifikasi' => [
                ['key' => 'id',                    'header' => 'ID'],
                ['key' => 'level',                 'header' => 'Level'],
                ['key' => 'nama',                  'header' => 'Nama Sertifikasi'],
                ['key' => 'penyelenggara',         'header' => 'Penyelenggara'],
                ['key' => 'tgl_sertifikat',        'header' => 'Tanggal Sertifikat'],
                ['key' => 'url_peserta',           'header' => 'URL Peserta'],
                ['key' => 'url_sertifikat',        'header' => 'URL Sertifikat'],
                ['key' => 'url_foto_upp',          'header' => 'URL Foto UPP'],
                ['key' => 'url_dokumen_undangan',  'header' => 'URL Undangan'],
                ['key' => 'keterangan',            'header' => 'Keterangan'],
                ['key' => 'status_internal',       'header' => 'Status'],
                ['key' => 'alasan_penolakan',      'header' => 'Alasan Penolakan'],
                ['key' => 'pusat_kemdikbud_id',    'header' => 'ID Kemdikti'],
                ['key' => 'creator_name',          'header' => 'Diajukan Oleh'],
                ['key' => 'approver_name',         'header' => 'Diverifikasi Oleh'],
                ['key' => 'created_at',            'header' => 'Tanggal Pengajuan'],
                ['key' => 'approved_at',           'header' => 'Tanggal Verifikasi'],
            ],
            default => [],
        };
    }

    /**
     * Build header row lengkap: [No] + [Kolom Statis] + [Mahasiswa Dinamis] + [Dosen Dinamis]
     */
    private function buildHeaderRow(): array
    {
        $headers = ['No'];

        // Kolom statis
        foreach ($this->getStaticColumns() as $col) {
            $headers[] = $col['header'];
        }

        // Kolom mahasiswa dinamis (Ketua + Anggota 1..N)
        $headers[] = 'Ketua (NIM)';
        $headers[] = 'Ketua (Nama)';
        for ($i = 1; $i < $this->maxMahasiswa; $i++) {
            $headers[] = "Anggota {$i} (NIM)";
            $headers[] = "Anggota {$i} (Nama)";
        }

        // Kolom dosen dinamis
        for ($i = 0; $i < $this->maxDosen; $i++) {
            $label = 'Dosen ' . ($i + 1);
            $headers[] = "{$label} (NUPTK)";
            $headers[] = "{$label} (Nama)";
            $headers[] = "{$label} (URL Surat Tugas)";
        }

        $this->totalColumns = count($headers);
        return $headers;
    }

    /**
     * Build satu baris data dari model pengajuan.
     */
    private function buildDataRow($item, int $rowNumber): array
    {
        $row = [$rowNumber];

        // Kolom statis
        foreach ($this->getStaticColumns() as $col) {
            $row[] = $this->resolveValue($item, $col['key']);
        }

        // Kolom mahasiswa (urutan sudah di-order by pivot urutan di Model)
        $mahasiswaList = $item->mahasiswa ? $item->mahasiswa->values() : collect();
        for ($i = 0; $i < $this->maxMahasiswa; $i++) {
            if (isset($mahasiswaList[$i])) {
                $row[] = $mahasiswaList[$i]->nim;
                $row[] = $mahasiswaList[$i]->nama;
            } else {
                $row[] = '-';
                $row[] = '-';
            }
        }

        // Kolom dosen
        $dosenList = $item->dosen ? $item->dosen->values() : collect();
        for ($i = 0; $i < $this->maxDosen; $i++) {
            if (isset($dosenList[$i])) {
                $row[] = $dosenList[$i]->nuptk;
                $row[] = $dosenList[$i]->nama;
                $row[] = $dosenList[$i]->pivot->url_surat_tugas ?? '-';
            } else {
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
            }
        }

        return $row;
    }

    /**
     * Resolve nilai field dari model, termasuk relasi dan formatting.
     */
    private function resolveValue($item, string $key): string
    {
        return match ($key) {
            'creator_name'  => $item->creator?->name ?? '-',
            'approver_name' => $item->approver?->name ?? '-',
            'created_at'    => $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-',
            'approved_at'   => $item->approved_at ? $item->approved_at->format('d/m/Y H:i') : '-',
            'tgl_sertifikat'=> $item->tgl_sertifikat ? $item->tgl_sertifikat->format('d/m/Y') : '-',
            default         => $this->castEnumValue($item->$key ?? null),
        };
    }

    /**
     * Cast enum backing value ke string agar tidak error di Excel.
     */
    private function castEnumValue($value): string
    {
        if ($value === null) {
            return '-';
        }

        // PHP Enum → ambil backing value
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * FromArray implementation — return seluruh data sebagai array rows.
     */
    public function array(): array
    {
        $rows = [];
        $rows[] = $this->buildHeaderRow();

        $rowNumber = 1;
        foreach ($this->data as $item) {
            $rows[] = $this->buildDataRow($item, $rowNumber);
            $rowNumber++;
        }

        return $rows;
    }

    /**
     * Sheet title.
     */
    public function title(): string
    {
        return match ($this->tipeKegiatan) {
            'prestasi'    => 'Prestasi Mandiri',
            'rekognisi'   => 'Rekognisi',
            'sertifikasi' => 'Sertifikasi',
            default       => 'Data',
        };
    }

    /**
     * Event listeners untuk styling Excel.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $this->getColumnLetter($this->totalColumns);

                // 1. AutoFilter — dropdown sort/filter di setiap kolom header
                $sheet->setAutoFilter("A1:{$lastCol}1");

                // 2. Freeze header row — header tetap terlihat saat scroll ke bawah
                $sheet->freezePane('A2');

                // 3. Bold + background color header
                $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
                $headerStyle->getFont()->setBold(true);
                $headerStyle->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD9E1F2'); // Light blue

                // 4. Border untuk seluruh data
                $lastRow = $this->data->count() + 1;
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // 5. Auto-width per kolom
                for ($i = 1; $i <= $this->totalColumns; $i++) {
                    $colLetter = $this->getColumnLetter($i);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }
            },
        ];
    }

    /**
     * Convert column number (1-based) ke Excel letter (A, B, ..., Z, AA, AB, ...).
     */
    private function getColumnLetter(int $columnNumber): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber);
    }
}
