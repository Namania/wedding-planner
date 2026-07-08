<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCatererRequest;
use App\Http\Requests\UpdateCatererRequest;
use App\Http\Resources\CatererResource;
use App\Models\Caterer;
use OpenApi\Attributes as OA;

class CatererController extends Controller
{
    #[OA\Get(
        path: '/api/caterers',
        summary: 'Liste des traiteurs',
        tags: ['Caterers'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des traiteurs, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Caterer'))
            ),
        ]
    )]
    public function index()
    {
        return CatererResource::collection(Caterer::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/caterers',
        summary: 'Créer un traiteur',
        tags: ['Caterers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CatererInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Traiteur créé',
                content: new OA\JsonContent(ref: '#/components/schemas/Caterer')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreCatererRequest $request)
    {
        $caterer = Caterer::create($request->validated());

        return new CatererResource($caterer);
    }

    #[OA\Get(
        path: '/api/caterers/{caterer}',
        summary: 'Détail d\'un traiteur',
        tags: ['Caterers'],
        parameters: [
            new OA\Parameter(name: 'caterer', in: 'path', required: true, description: 'ID du traiteur', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Traiteur trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/Caterer')
            ),
            new OA\Response(response: 404, description: 'Traiteur introuvable'),
        ]
    )]
    public function show(Caterer $caterer)
    {
        return new CatererResource($caterer);
    }

    #[OA\Put(
        path: '/api/caterers/{caterer}',
        summary: 'Mettre à jour un traiteur',
        tags: ['Caterers'],
        parameters: [
            new OA\Parameter(name: 'caterer', in: 'path', required: true, description: 'ID du traiteur', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CatererInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Traiteur mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Caterer')
            ),
            new OA\Response(response: 404, description: 'Traiteur introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateCatererRequest $request, Caterer $caterer)
    {
        $caterer->update($request->validated());

        return new CatererResource($caterer);
    }

    #[OA\Delete(
        path: '/api/caterers/{caterer}',
        summary: 'Supprimer un traiteur',
        tags: ['Caterers'],
        parameters: [
            new OA\Parameter(name: 'caterer', in: 'path', required: true, description: 'ID du traiteur', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Traiteur supprimé',
                content: new OA\JsonContent(ref: '#/components/schemas/Caterer')
            ),
            new OA\Response(response: 404, description: 'Traiteur introuvable'),
        ]
    )]
    public function destroy(Caterer $caterer)
    {
        $caterer->delete();

        return new CatererResource($caterer);
    }
}
