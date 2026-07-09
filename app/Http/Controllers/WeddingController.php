<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWeddingRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use OpenApi\Attributes as OA;

class WeddingController extends Controller
{
    #[OA\Get(
        path: '/api/wedding',
        summary: 'Informations du mariage (mariés & date)',
        tags: ['Wedding'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mariage courant',
                content: new OA\JsonContent(ref: '#/components/schemas/Wedding')
            ),
        ]
    )]
    public function show()
    {
        return new WeddingResource(Wedding::firstOrCreate());
    }

    #[OA\Put(
        path: '/api/wedding',
        summary: 'Mettre à jour les informations du mariage',
        tags: ['Wedding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/WeddingInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mariage mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Wedding')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateWeddingRequest $request)
    {
        $wedding = Wedding::firstOrCreate();
        $wedding->update($request->validated());

        return new WeddingResource($wedding);
    }
}
