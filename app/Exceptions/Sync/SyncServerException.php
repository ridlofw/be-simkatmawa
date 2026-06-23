<?php

namespace App\Exceptions\Sync;

use App\Enums\SyncErrorCode;

/**
 * Exception saat server Kemdikti down (5xx) atau network error.
 * Bisa di-retry dengan exponential backoff.
 */
class SyncServerException extends SyncException
{
    public function __construct(
        string $message = 'Server Kemdikti sedang bermasalah.',
        SyncErrorCode $errorCode = SyncErrorCode::SERVER_ERROR,
        ?array $errorDetail = null,
    ) {
        parent::__construct($message, $errorCode, $errorDetail);
    }
}
