<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Expense',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 1250.5),
        new OA\Property(property: 'note', type: 'string', example: 'Acompte traiteur'),
        new OA\Property(property: 'paid_by', type: 'string', example: 'Margaux'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ExpenseInput',
    type: 'object',
    required: ['amount', 'note', 'paid_by'],
    properties: [
        new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 0, exclusiveMinimum: true, example: 1250.5),
        new OA\Property(property: 'note', type: 'string', maxLength: 255, example: 'Acompte traiteur'),
        new OA\Property(property: 'paid_by', type: 'string', maxLength: 80, example: 'Margaux'),
    ]
)]
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'note' => $this->note,
            'paid_by' => $this->paid_by,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
