<?php

namespace App\Http\Resources;

use App\Models\Florist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Florist',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Fleurs de Margaux'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 1200),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0612345678'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, example: 'https://fleurs-de-margaux.fr'),
        new OA\Property(property: 'style', type: 'string', enum: Florist::STYLES, nullable: true, example: 'champetre'),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Très bon feeling, propose des fleurs de saison.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'FloristInput',
    type: 'object',
    required: ['name', 'price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Fleurs de Margaux'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'style', type: 'string', enum: Florist::STYLES, nullable: true),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ]
)]
class FloristResource extends JsonResource
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
            'price' => (float) $this->price,
            'phone' => $this->phone,
            'website' => $this->website,
            'style' => $this->style,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
