<?php

namespace App\Traits;

trait HasPagination
{
    /**
     * Get the number of items per page for pagination.
     *
     * @param int|string|null $limit Optional requested limit.
     * @return int
     */
    public function getPaginationLimit(int|string|null $limit = null): int
    {
        $limit = (int) $limit;
        
        // Default pagination limit, overriding the need for config('pagination.per_page')
        $defaultLimit = 10;
        
        return $limit > 0 ? $limit : $defaultLimit;
    }
}
