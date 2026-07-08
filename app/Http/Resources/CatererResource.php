<?php

namespace App\Http\Resources;

use App\Models\Caterer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Caterer',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Saveurs & Co'),
        new OA\Property(property: 'price_per_person', type: 'number', format: 'float', example: 65),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0612345678'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, example: 'https://saveurs-and-co.fr'),
        new OA\Property(property: 'service_type', type: 'string', enum: Caterer::SERVICE_TYPES, nullable: true, example: 'seated'),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Bonne dégustation, menu végétarien disponible.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'CatererInput',
    type: 'object',
    required: ['name', 'price_per_person'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Saveurs & Co'),
        new OA\Property(property: 'price_per_person', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'service_type', type: 'string', enum: Caterer::SERVICE_TYPES, nullable: true),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ]
)]
class CatererResource extends JsonResource
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
            'price_per_person' => (float) $this->price_per_person,
            'phone' => $this->phone,
            'website' => $this->website,
            'service_type' => $this->service_type,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
