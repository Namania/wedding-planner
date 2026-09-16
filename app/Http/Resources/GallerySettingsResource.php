<?php

namespace App\Http\Resources;

use App\Models\GalleryGuest;
use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GallerySettings',
    type: 'object',
    properties: [
        new OA\Property(property: 'invite_token', type: 'string', description: 'Token à encoder dans le QR code (admin uniquement)'),
        new OA\Property(property: 'registrations_open', type: 'boolean', example: true),
        new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'max_guests', type: 'integer', example: 200),
        new OA\Property(property: 'max_photos_per_guest', type: 'integer', example: 100),
        new OA\Property(property: 'guests_count', type: 'integer', example: 42),
        new OA\Property(property: 'photos_count', type: 'integer', example: 318),
    ]
)]
#[OA\Schema(
    schema: 'GallerySettingsInput',
    type: 'object',
    properties: [
        new OA\Property(property: 'registrations_open', type: 'boolean', example: true),
        new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'max_guests', type: 'integer', example: 200),
        new OA\Property(property: 'max_photos_per_guest', type: 'integer', example: 100),
    ]
)]
class GallerySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'invite_token' => $this->invite_token,
            'registrations_open' => $this->registrations_open,
            'registration_closes_at' => $this->registration_closes_at?->toISOString(),
            'max_guests' => $this->max_guests,
            'max_photos_per_guest' => $this->max_photos_per_guest,
            'guests_count' => GalleryGuest::count(),
            'photos_count' => GalleryPhoto::count(),
        ];
    }
}
