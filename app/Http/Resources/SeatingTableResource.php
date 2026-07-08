<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SeatingTable',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Table 1'),
        new OA\Property(property: 'capacity', type: 'integer', example: 8),
        new OA\Property(
            property: 'guests',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'confirmed', type: 'boolean', nullable: true),
                ]
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'SeatingTableInput',
    type: 'object',
    required: ['name', 'capacity'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Table 1'),
        new OA\Property(property: 'capacity', type: 'integer', minimum: 1, example: 8),
    ]
)]
class SeatingTableResource extends JsonResource
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
            'capacity' => $this->capacity,
            'guests' => $this->guests->map(fn ($guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'confirmed' => $guest->confirmed,
            ]),
        ];
    }
}
