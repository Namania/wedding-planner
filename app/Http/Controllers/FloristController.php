<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloristRequest;
use App\Http\Requests\UpdateFloristRequest;
use App\Http\Resources\FloristResource;
use App\Models\Florist;
use OpenApi\Attributes as OA;

class FloristController extends Controller
{
    #[OA\Get(
        path: '/api/florists',
        summary: 'Liste des fleuristes',
        tags: ['Florists'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des fleuristes, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Florist'))
            ),
        ]
    )]
    public function index()
    {
        return FloristResource::collection(Florist::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/florists',
        summary: 'Créer un fleuriste',
        tags: ['Florists'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FloristInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Fleuriste créé',
                content: new OA\JsonContent(ref: '#/components/schemas/Florist')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreFloristRequest $request)
    {
        $florist = Florist::create($request->validated());

        return new FloristResource($florist);
    }

    #[OA\Get(
        path: '/api/florists/{florist}',
        summary: 'Détail d\'un fleuriste',
        tags: ['Florists'],
        parameters: [
            new OA\Parameter(name: 'florist', in: 'path', required: true, description: 'ID du fleuriste', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fleuriste trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/Florist')
            ),
            new OA\Response(response: 404, description: 'Fleuriste introuvable'),
        ]
    )]
    public function show(Florist $florist)
    {
        return new FloristResource($florist);
    }

    #[OA\Put(
        path: '/api/florists/{florist}',
        summary: 'Mettre à jour un fleuriste',
        tags: ['Florists'],
        parameters: [
            new OA\Parameter(name: 'florist', in: 'path', required: true, description: 'ID du fleuriste', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FloristInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fleuriste mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Florist')
            ),
            new OA\Response(response: 404, description: 'Fleuriste introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateFloristRequest $request, Florist $florist)
    {
        $florist->update($request->validated());

        return new FloristResource($florist);
    }

    #[OA\Delete(
        path: '/api/florists/{florist}',
        summary: 'Supprimer un fleuriste',
        tags: ['Florists'],
        parameters: [
            new OA\Parameter(name: 'florist', in: 'path', required: true, description: 'ID du fleuriste', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fleuriste supprimé',
                content: new OA\JsonContent(ref: '#/components/schemas/Florist')
            ),
            new OA\Response(response: 404, description: 'Fleuriste introuvable'),
        ]
    )]
    public function destroy(Florist $florist)
    {
        $florist->delete();

        return new FloristResource($florist);
    }
}
