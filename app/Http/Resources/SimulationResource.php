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
        new OA\Property(property: 'animation_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
        new OA\Property(property: 'outfit_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
        new OA\Property(property: 'venue', ref: '#/components/schemas/Venue', nullable: true),
        new OA\Property(property: 'caterer', ref: '#/components/schemas/Caterer', nullable: true),
        new OA\Property(property: 'florist', ref: '#/components/schemas/Florist', nullable: true),
        new OA\Property(property: 'animations', type: 'array', items: new OA\Items(ref: '#/components/schemas/Animation')),
        new OA\Property(property: 'outfits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Outfit')),
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
        new OA\Property(property: 'animation_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Remplace la liste complète des animations retenues.'),
        new OA\Property(property: 'outfit_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Remplace la liste complète des tenues retenues.'),
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
            'animation_ids' => $this->animations->pluck('id'),
            'outfit_ids' => $this->outfits->pluck('id'),
            'venue' => $this->venue ? new VenueResource($this->venue) : null,
            'caterer' => $this->caterer ? new CatererResource($this->caterer) : null,
            'florist' => $this->florist ? new FloristResource($this->florist) : null,
            'animations' => AnimationResource::collection($this->animations),
            'outfits' => OutfitResource::collection($this->outfits),
        ];
    }
}
