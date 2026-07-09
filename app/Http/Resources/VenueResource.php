<?php

namespace App\Http\Resources;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Venue',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Domaine des Roses'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, example: 'https://domaine-des-roses.fr'),
        new OA\Property(property: 'maps_url', type: 'string', format: 'uri', nullable: true, example: 'https://maps.google.com/?q=Domaine+des+Roses'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 3500),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Beau parc, mais salle un peu petite pour 80 invités.'),
        new OA\Property(property: 'quote_status', type: 'string', enum: Venue::QUOTE_STATUSES, nullable: true, example: 'requested'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'VenueInput',
    type: 'object',
    required: ['name', 'price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Domaine des Roses'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'maps_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'note', type: 'string', nullable: true),
        new OA\Property(property: 'quote_status', type: 'string', enum: Venue::QUOTE_STATUSES, nullable: true),
    ]
)]
class VenueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website' => $this->website,
            'maps_url' => $this->maps_url,
            'price' => (float) $this->price,
            'note' => $this->note,
            'quote_status' => $this->quote_status,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
