<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GalleryPhoto',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'guest_id', type: 'integer', example: 3),
        new OA\Property(property: 'guest_name', type: 'string', example: 'Camille'),
        new OA\Property(property: 'caption', type: 'string', nullable: true, example: 'Ouverture du bal !'),
        new OA\Property(property: 'width', type: 'integer', example: 2560),
        new OA\Property(property: 'height', type: 'integer', example: 1707),
        new OA\Property(property: 'hidden', type: 'boolean', example: false),
        new OA\Property(property: 'thumb_url', type: 'string', description: 'URL signée temporaire de la miniature'),
        new OA\Property(property: 'full_url', type: 'string', description: 'URL signée temporaire de la version web'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class GalleryPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expiration = now()->addHours((int) config('gallery.signed_url_ttl_hours'));

        return [
            'id' => $this->id,
            'guest_id' => $this->gallery_guest_id,
            'guest_name' => $this->guest?->name,
            'caption' => $this->caption,
            'width' => $this->width,
            'height' => $this->height,
            'hidden' => $this->isHidden(),
            'thumb_url' => URL::temporarySignedRoute('gallery.photos.file', $expiration, [
                'photo' => $this->id,
                'variant' => 'thumb',
            ]),
            'full_url' => URL::temporarySignedRoute('gallery.photos.file', $expiration, [
                'photo' => $this->id,
                'variant' => 'full',
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
