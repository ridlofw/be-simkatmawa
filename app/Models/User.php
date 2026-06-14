<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasDeletedBy;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUuids, SoftDeletes, LogsActivity, HasRoles, HasDeletedBy;

    /**
     * The attributes that are mass assignable.
     * Kolom `role` DIHAPUS — role dikelola via Spatie Permission (model_has_roles).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_login_at',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Konfigurasi Spatie Activity Log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->useLogName('user');
    }

    // ========== RELASI ==========

    /**
     * Relasi ke data mahasiswa (jika user adalah mahasiswa).
     */
    public function mahasiswa(): HasOne
    {
        return $this->hasOne(Mahasiswa::class, 'user_id');
    }

    /**
     * Prestasi yang dibuat oleh user ini.
     */
    public function createdPrestasi(): HasMany
    {
        return $this->hasMany(PrestasiMandiri::class, 'created_by');
    }

    /**
     * Prestasi yang di-approve oleh user ini (Admin).
     */
    public function approvedPrestasi(): HasMany
    {
        return $this->hasMany(PrestasiMandiri::class, 'approved_by');
    }
}
