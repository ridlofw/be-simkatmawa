<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ActivityLogCollection extends ResourceCollection
{
    public $collects = ActivityLogResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Activity log berhasil diambil.',
            'errors' => null,
        ];
    }
}
