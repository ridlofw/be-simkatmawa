<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Dosen extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'dosen';
    protected $primaryKey = 'nuptk';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'nuptk',
        'nama',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nuptk', 'nama'])
            ->logOnlyDirty()
            ->useLogName('dosen');
    }

    // ========== RELASI ==========

    public function prestasiMandiri(): BelongsToMany
    {
        return $this->belongsToMany(PrestasiMandiri::class, 'prestasi_mandiri_dosen', 'nuptk', 'prestasi_mandiri_id')
                    ->withPivot('url_surat_tugas');
    }

    public function sertifikasi(): BelongsToMany
    {
        return $this->belongsToMany(Sertifikasi::class, 'sertifikasi_dosen', 'nuptk', 'sertifikasi_id')
                    ->withPivot('url_surat_tugas');
    }

    public function rekognisi(): BelongsToMany
    {
        return $this->belongsToMany(Rekognisi::class, 'rekognisi_dosen', 'nuptk', 'rekognisi_id')
                    ->withPivot('url_surat_tugas');
    }
}
