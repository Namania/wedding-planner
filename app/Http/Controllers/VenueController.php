<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenueRequest;
use App\Http\Requests\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use OpenApi\Attributes as OA;

class VenueController extends Controller
{
    #[OA\Get(
        path: '/api/venues',
        summary: 'Liste des lieux',
        tags: ['Venues'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des lieux, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Venue'))
            ),
        ]
    )]
    public function index()
    {
        return VenueResource::collection(Venue::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/venues',
        summary: 'Créer un lieu',
        tags: ['Venues'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VenueInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Lieu créé',
                content: new OA\JsonContent(ref: '#/components/schemas/Venue')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreVenueRequest $request)
    {
        $venue = Venue::create($request->validated());

        return new VenueResource($venue);
    }

    #[OA\Get(
        path: '/api/venues/{venue}',
        summary: 'Détail d\'un lieu',
        tags: ['Venues'],
        parameters: [
            new OA\Parameter(name: 'venue', in: 'path', required: true, description: 'ID du lieu', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieu trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/Venue')
            ),
            new OA\Response(response: 404, description: 'Lieu introuvable'),
        ]
    )]
    public function show(Venue $venue)
    {
        return new VenueResource($venue);
    }

    #[OA\Put(
        path: '/api/venues/{venue}',
        summary: 'Mettre à jour un lieu',
        tags: ['Venues'],
        parameters: [
            new OA\Parameter(name: 'venue', in: 'path', required: true, description: 'ID du lieu', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VenueInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieu mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Venue')
            ),
            new OA\Response(response: 404, description: 'Lieu introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateVenueRequest $request, Venue $venue)
    {
        $venue->update($request->validated());

        return new VenueResource($venue);
    }

    #[OA\Delete(
        path: '/api/venues/{venue}',
        summary: 'Supprimer un lieu',
        tags: ['Venues'],
        parameters: [
            new OA\Parameter(name: 'venue', in: 'path', required: true, description: 'ID du lieu', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieu supprimé',
                content: new OA\JsonContent(ref: '#/components/schemas/Venue')
            ),
            new OA\Response(response: 404, description: 'Lieu introuvable'),
        ]
    )]
    public function destroy(Venue $venue)
    {
        $venue->delete();

        return new VenueResource($venue);
    }
}
