<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Guest',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jean Dupont'),
        new OA\Property(property: 'role', type: 'string', enum: ['witness', 'groomsman', 'bridesmaid'], nullable: true, example: 'witness'),
        new OA\Property(property: 'confirmed', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'seating_table_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'GuestInput',
    type: 'object',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jean Dupont'),
        new OA\Property(property: 'role', type: 'string', enum: ['witness', 'groomsman', 'bridesmaid'], nullable: true),
        new OA\Property(property: 'confirmed', type: 'boolean', nullable: true),
        new OA\Property(property: 'seating_table_id', type: 'integer', nullable: true),
    ]
)]
class GuestResource extends JsonResource
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
            'role' => $this->role,
            'confirmed' => $this->confirmed,
            'seating_table_id' => $this->seating_table_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
