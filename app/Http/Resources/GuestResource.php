<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Un compte invité vu par la galerie. Remplace GalleryGuestResource en
 * conservant sa forme, que le front consomme telle quelle ; seul l'email
 * s'y ajoute.
 *
 * Schéma nommé `GalleryAccount` (et non `Guest`) : ce nom est déjà pris par
 * WeddingGuestResource, qui décrit les invités de la liste du mariage — un
 * domaine sans rapport avec les comptes de la galerie photo.
 */
#[OA\Schema(
    schema: 'GalleryAccount',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'name', type: 'string', example: 'Camille'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@exemple.com'),
        new OA\Property(property: 'banned', type: 'boolean', example: false),
        new OA\Property(property: 'photos_count', type: 'integer', nullable: true, example: 12),
        new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
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
