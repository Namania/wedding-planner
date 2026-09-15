<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Wedding',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'spouse_1_name', type: 'string', example: 'TOTO1'),
        new OA\Property(property: 'spouse_2_name', type: 'string', example: 'TOTO2'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2028-01-01'),
    ]
)]
#[OA\Schema(
    schema: 'WeddingInput',
    type: 'object',
    required: ['spouse_1_name', 'spouse_2_name', 'date'],
    properties: [
        new OA\Property(property: 'spouse_1_name', type: 'string', example: 'TOTO1'),
        new OA\Property(property: 'spouse_2_name', type: 'string', example: 'TOTO2'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2028-01-01'),
    ]
)]
class WeddingResource extends JsonResource
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
            'spouse_1_name' => $this->spouse_1_name,
            'spouse_2_name' => $this->spouse_2_name,
            'date' => $this->date->toDateString(),
        ];
    }
}
