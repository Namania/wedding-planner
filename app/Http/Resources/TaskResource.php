<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Task',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Réserver la salle'),
        new OA\Property(property: 'category', type: 'string', enum: Task::CATEGORIES, nullable: true, example: 'prestataires'),
        new OA\Property(property: 'status', type: 'string', enum: Task::STATUSES, example: 'todo'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2027-03-15'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'TaskInput',
    type: 'object',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Réserver la salle'),
        new OA\Property(property: 'category', type: 'string', enum: Task::CATEGORIES, nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: Task::STATUSES),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
    ]
)]
class TaskResource extends JsonResource
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
            'category' => $this->category,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
