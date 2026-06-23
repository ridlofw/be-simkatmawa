<?php

namespace App\Exceptions\Sync;

use App\Enums\SyncErrorCode;

/**
 * Base exception untuk seluruh error sinkronisasi Kemdikti.
 * Menyimpan error_code, error_message, dan error_detail untuk logging ke sync_queue.
 */
class SyncException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly SyncErrorCode $errorCode,
        public readonly ?array $errorDetail = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
