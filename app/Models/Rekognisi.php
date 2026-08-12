<?php

namespace App\Models;

use App\Enums\JenisRekognisi;
use App\Enums\Level;
use App\Enums\StatusInternal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\HasDeletedBy;

class Rekognisi extends Model
{
    use SoftDeletes, LogsActivity, HasDeletedBy;

    protected $table = 'rekognisi';

    protected $fillable = [
        'level',
        'jenis',
        'nama',
        'penyelenggara',
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
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'jenis' => JenisRekognisi::class,
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
            ->useLogName('rekognisi');
    }

    // ========== RELASI ==========

    /** Mahasiswa yang terlibat (pivot many-to-many, diurutkan berdasarkan posisi: 0=Ketua). */
    public function mahasiswa(): BelongsToMany
    {
        return $this->belongsToMany(Mahasiswa::class, 'rekognisi_mahasiswa', 'rekognisi_id', 'nim')
                    ->withPivot('urutan')
                    ->orderByPivot('urutan');
    }

    public function dosen(): BelongsToMany
    {
        return $this->belongsToMany(Dosen::class, 'rekognisi_dosen', 'rekognisi_id', 'nuptk')
                    ->withPivot('url_surat_tugas');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
