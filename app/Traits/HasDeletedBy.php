<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasDeletedBy
{
    /**
     * Boot the trait to automatically set deleted_by when model is being soft deleted.
     */
    protected static function bootHasDeletedBy()
    {
        static::deleting(function ($model) {
            // Pastikan model menggunakan SoftDeletes dan aksi ini bukan force delete
            if (in_array(SoftDeletes::class, class_uses_recursive($model)) && !$model->isForceDeleting()) {
                if (auth()->check()) {
                    $model->deleted_by = auth()->id();
                    $model->saveQuietly(); // Simpan deleted_by tanpa memicu event updating lainnya
                }
            }
        });

        static::restoring(function ($model) {
            // Bersihkan deleted_by saat di-restore
            $model->deleted_by = null;
            $model->saveQuietly();
        });
    }

    /**
     * Relasi ke User yang menghapus (mendelete) data ini.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
