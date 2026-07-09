<?php

namespace App\Http\Resources;

use App\Models\Animation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Animation',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'DJ Max'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 900),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0612345678'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, example: 'https://dj-max.fr'),
        new OA\Property(property: 'type', type: 'string', enum: Animation::TYPES, nullable: true, example: 'dj'),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Bon feeling, propose une playlist personnalisée.'),
        new OA\Property(property: 'quote_status', type: 'string', enum: Animation::QUOTE_STATUSES, nullable: true, example: 'requested'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'AnimationInput',
    type: 'object',
    required: ['name', 'price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'DJ Max'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'type', type: 'string', enum: Animation::TYPES, nullable: true),
        new OA\Property(property: 'note', type: 'string', nullable: true),
        new OA\Property(property: 'quote_status', type: 'string', enum: Animation::QUOTE_STATUSES, nullable: true),
    ]
)]
class AnimationResource extends JsonResource
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
            'type' => $this->type,
            'note' => $this->note,
            'quote_status' => $this->quote_status,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
