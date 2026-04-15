<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Mahasiswa extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'mahasiswa';
    protected $primaryKey = 'nim';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Tabel mahasiswa tidak memerlukan timestamps

    protected $fillable = [
        'nim',
        'nama',
        'user_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nim', 'nama', 'user_id'])
            ->logOnlyDirty()
            ->useLogName('mahasiswa');
    }

    // ========== RELASI ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function prestasiMandiri(): BelongsToMany
    {
        return $this->belongsToMany(PrestasiMandiri::class, 'prestasi_mandiri_mahasiswa', 'nim', 'prestasi_mandiri_id');
    }

    public function sertifikasi(): BelongsToMany
    {
        return $this->belongsToMany(Sertifikasi::class, 'sertifikasi_mahasiswa', 'nim', 'sertifikasi_id');
    }

    public function rekognisi(): BelongsToMany
    {
        return $this->belongsToMany(Rekognisi::class, 'rekognisi_mahasiswa', 'nim', 'rekognisi_id');
    }
}
