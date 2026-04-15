<?php

namespace App\Models;

use App\Enums\Bentuk;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use App\Enums\StatusInternal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PrestasiMandiri extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'prestasi_mandiri';

    protected $fillable = [
        'level',
        'kategori',
        'lomba',
        'cabang',
        'penyelenggara',
        'peringkat',
        'jumlah_unit_peserta',
        'kelompok_prestasi',
        'bentuk',
        'url_peserta',
        'url_sertifikat',
        'tgl_sertifikat',
        'url_foto_upp',
        'url_dokumen_undangan',
        'keterangan',
        'status_internal',
        'alasan_penolakan',
        'pusat_kemdikbud_id',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'kategori' => KategoriPrestasi::class,
            'peringkat' => Peringkat::class,
            'kelompok_prestasi' => KelompokPrestasi::class,
            'bentuk' => Bentuk::class,
            'status_internal' => StatusInternal::class,
            'tgl_sertifikat' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('prestasi_mandiri');
    }

    // ========== RELASI ==========

    /** Mahasiswa yang terlibat (pivot many-to-many). */
    public function mahasiswa(): BelongsToMany
    {
        return $this->belongsToMany(Mahasiswa::class, 'prestasi_mandiri_mahasiswa', 'prestasi_mandiri_id', 'nim');
    }

    /** Dosen pembimbing yang terlibat (pivot many-to-many + url_surat_tugas). */
    public function dosen(): BelongsToMany
    {
        return $this->belongsToMany(Dosen::class, 'prestasi_mandiri_dosen', 'prestasi_mandiri_id', 'nuptk')
                    ->withPivot('url_surat_tugas');
    }

    /** User yang membuat pengajuan ini. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Admin/User yang meng-approve. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
