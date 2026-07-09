<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Simulation',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Défaut'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'venue_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'caterer_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'florist_id', type: 'integer', nullable: true, example: 1),
    ]
)]
#[OA\Schema(
    schema: 'SimulationInput',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Option romantique'),
        new OA\Property(property: 'venue_id', type: 'integer', nullable: true),
        new OA\Property(property: 'caterer_id', type: 'integer', nullable: true),
        new OA\Property(property: 'florist_id', type: 'integer', nullable: true),
    ]
)]
class SimulationResource extends JsonResource
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
            'is_active' => $this->is_active,
            'venue_id' => $this->venue_id,
            'caterer_id' => $this->caterer_id,
            'florist_id' => $this->florist_id,
        ];
    }
}
