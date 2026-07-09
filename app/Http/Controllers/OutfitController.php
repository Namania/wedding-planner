<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOutfitRequest;
use App\Http\Requests\UpdateOutfitRequest;
use App\Http\Resources\OutfitResource;
use App\Models\Outfit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class OutfitController extends Controller
{
    #[OA\Get(
        path: '/api/outfits',
        summary: 'Liste des tenues',
        tags: ['Outfits'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tenues, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Outfit'))
            ),
        ]
    )]
    public function index()
    {
        return OutfitResource::collection(Outfit::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/outfits',
        summary: 'Créer une tenue',
        tags: ['Outfits'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name', 'price', 'spouse'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Robe champêtre - Maison Rose'),
                        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
                        new OA\Property(property: 'spouse', type: 'string', enum: ['spouse_1', 'spouse_2']),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'note', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tenue créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Outfit')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreOutfitRequest $request)
    {
        $data = $request->validated();
        $data['image_path'] = $request->file('image')?->store('outfits', 'public');

        $outfit = Outfit::create($data);

        return new OutfitResource($outfit);
    }

    #[OA\Get(
        path: '/api/outfits/{outfit}',
        summary: 'Détail d\'une tenue',
        tags: ['Outfits'],
        parameters: [
            new OA\Parameter(name: 'outfit', in: 'path', required: true, description: 'ID de la tenue', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenue trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/Outfit')
            ),
            new OA\Response(response: 404, description: 'Tenue introuvable'),
        ]
    )]
    public function show(Outfit $outfit)
    {
        return new OutfitResource($outfit);
    }

    #[OA\Post(
        path: '/api/outfits/{outfit}',
        summary: 'Mettre à jour une tenue',
        description: 'Utilise le spoofing de méthode Laravel (`_method=PUT`) pour accepter un upload de fichier.',
        tags: ['Outfits'],
        parameters: [
            new OA\Parameter(name: 'outfit', in: 'path', required: true, description: 'ID de la tenue', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['name', 'price', 'spouse'],
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT'),
                        new OA\Property(property: 'name', type: 'string', example: 'Robe champêtre - Maison Rose'),
                        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0),
                        new OA\Property(property: 'spouse', type: 'string', enum: ['spouse_1', 'spouse_2']),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'note', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenue mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Outfit')
            ),
            new OA\Response(response: 404, description: 'Tenue introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateOutfitRequest $request, Outfit $outfit)
    {
        $data = Arr::except($request->validated(), 'remove_image');

        if ($request->hasFile('image')) {
            if ($outfit->image_path) {
                Storage::disk('public')->delete($outfit->image_path);
            }
            $data['image_path'] = $request->file('image')->store('outfits', 'public');
        } elseif ($request->boolean('remove_image') && $outfit->image_path) {
            Storage::disk('public')->delete($outfit->image_path);
            $data['image_path'] = null;
        }

        $outfit->update($data);

        return new OutfitResource($outfit);
    }

    #[OA\Delete(
        path: '/api/outfits/{outfit}',
        summary: 'Supprimer une tenue',
        tags: ['Outfits'],
        parameters: [
            new OA\Parameter(name: 'outfit', in: 'path', required: true, description: 'ID de la tenue', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tenue supprimée'),
            new OA\Response(response: 404, description: 'Tenue introuvable'),
        ]
    )]
    public function destroy(Outfit $outfit)
    {
        if ($outfit->image_path) {
            Storage::disk('public')->delete($outfit->image_path);
        }

        $outfit->delete();

        return new OutfitResource($outfit);
    }
}
