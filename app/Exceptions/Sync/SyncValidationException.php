<?php

namespace App\Exceptions\Sync;

use App\Enums\SyncErrorCode;

/**
 * Exception saat Kemdikti mengembalikan 422 — data tidak sesuai format.
 * TIDAK boleh di-retry (data harus diperbaiki manual).
 */
class SyncValidationException extends SyncException
{
    public function __construct(string $message = 'Data tidak sesuai format Kemdikti.', ?array $errorDetail = null)
    {
        parent::__construct($message, SyncErrorCode::VALIDATION_ERROR, $errorDetail);
    }
}
