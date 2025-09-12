<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokensResource extends JsonResource
{
    public function toArray($request): array
    {
        $tokens = is_array($this->resource) ? $this->resource : [];
        return [
            'accessToken'  => $tokens['accessToken']  ?? null,
            'refreshToken' => $tokens['refreshToken'] ?? null,
            'tokenType'    => $tokens['tokenType']    ?? 'Bearer',
            'expiresIn'    => $tokens['expiresIn']    ?? null,
        ];
    }
}
