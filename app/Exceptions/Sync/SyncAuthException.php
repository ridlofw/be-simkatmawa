<?php

namespace App\Exceptions\Sync;

use App\Enums\SyncErrorCode;

/**
 * Exception saat kredensial Kemdikti salah atau akun diblokir.
 * Harus memicu auto-pause queue.
 */
class SyncAuthException extends SyncException
{
    public function __construct(string $message = 'Kredensial Kemdikti tidak valid.', ?array $errorDetail = null)
    {
        parent::__construct($message, SyncErrorCode::AUTH_ERROR, $errorDetail);
    }
}
