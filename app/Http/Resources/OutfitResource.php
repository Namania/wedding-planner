<?php

namespace App\Http\Resources;

use App\Models\Outfit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Outfit',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Robe champêtre - Maison Rose'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 1200),
        new OA\Property(property: 'spouse', type: 'string', enum: Outfit::SPOUSES, example: 'spouse_1'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0612345678'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, example: 'https://maison-rose.fr'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/storage/outfits/xxx.jpg'),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Essayage prévu le mois prochain.'),
        new OA\Property(property: 'quote_status', type: 'string', enum: Outfit::QUOTE_STATUSES, nullable: true, example: 'requested'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class OutfitResource extends JsonResource
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
            'spouse' => $this->spouse,
            'phone' => $this->phone,
            'website' => $this->website,
            'image_url' => $this->image_path ? $request->getSchemeAndHttpHost() . '/storage/' . $this->image_path : null,
            'note' => $this->note,
            'quote_status' => $this->quote_status,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
