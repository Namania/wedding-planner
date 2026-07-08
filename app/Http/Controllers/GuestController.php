<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use OpenApi\Attributes as OA;

class GuestController extends Controller
{
    #[OA\Get(
        path: '/api/guests',
        summary: 'Liste des invités',
        tags: ['Guests'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des invités, du plus récent au plus ancien',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Guest'))
            ),
        ]
    )]
    public function index()
    {
        return GuestResource::collection(Guest::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/guests',
        summary: 'Créer un invité',
        tags: ['Guests'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GuestInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Invité créé',
                content: new OA\JsonContent(ref: '#/components/schemas/Guest')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreGuestRequest $request)
    {
        $guest = Guest::create($request->validated());

        return new GuestResource($guest);
    }

    #[OA\Get(
        path: '/api/guests/{guest}',
        summary: 'Détail d\'un invité',
        tags: ['Guests'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, description: 'ID de l\'invité', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invité trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/Guest')
            ),
            new OA\Response(response: 404, description: 'Invité introuvable'),
        ]
    )]
    public function show(Guest $guest)
    {
        return new GuestResource($guest);
    }

    #[OA\Put(
        path: '/api/guests/{guest}',
        summary: 'Mettre à jour un invité',
        tags: ['Guests'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, description: 'ID de l\'invité', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GuestInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invité mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Guest')
            ),
            new OA\Response(response: 404, description: 'Invité introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateGuestRequest $request, Guest $guest)
    {
        $guest->update($request->validated());

        return new GuestResource($guest);
    }

    #[OA\Delete(
        path: '/api/guests/{guest}',
        summary: 'Supprimer un invité',
        tags: ['Guests'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, description: 'ID de l\'invité', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invité supprimé',
                content: new OA\JsonContent(ref: '#/components/schemas/Guest')
            ),
            new OA\Response(response: 404, description: 'Invité introuvable'),
        ]
    )]
    public function destroy(Guest $guest)
    {
        $guest->delete();

        return new GuestResource($guest);
    }
}
