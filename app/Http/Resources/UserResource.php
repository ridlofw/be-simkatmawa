<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Ambil role dari Spatie Permission
        $role = $this->getRoleNames()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'identitas' => $this->when(
                $role === 'mahasiswa',
                fn() => $this->mahasiswa?->nim
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
