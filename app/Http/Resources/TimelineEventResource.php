<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TimelineEvent',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Cérémonie'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2027-03-20T14:00:00+01:00'),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Prévoir les alliances'),
    ]
)]
#[OA\Schema(
    schema: 'TimelineEventInput',
    type: 'object',
    required: ['title', 'starts_at'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Cérémonie'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ]
)]
class TimelineEventResource extends JsonResource
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
            'title' => $this->title,
            'starts_at' => $this->starts_at->toIso8601String(),
            'note' => $this->note,
        ];
    }
}
