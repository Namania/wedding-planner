<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un compte invité vu par la galerie. Remplace GalleryGuestResource en
 * conservant sa forme, que le front consomme telle quelle ; seul l'email
 * s'y ajoute.
 */
class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'banned' => $this->isBanned(),
            'photos_count' => $this->whenCounted('photos'),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
