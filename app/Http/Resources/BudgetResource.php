<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Budget',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 20000),
    ]
)]
#[OA\Schema(
    schema: 'BudgetInput',
    type: 'object',
    required: ['total'],
    properties: [
        new OA\Property(property: 'total', type: 'number', format: 'float', minimum: 0, example: 20000),
    ]
)]
class BudgetResource extends JsonResource
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
            'total' => (float) $this->total,
        ];
    }
}
