<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnimationRequest;
use App\Http\Requests\UpdateAnimationRequest;
use App\Http\Resources\AnimationResource;
use App\Models\Animation;
use OpenApi\Attributes as OA;

class AnimationController extends Controller
{
    #[OA\Get(
        path: '/api/animations',
        summary: 'Liste des animations',
        tags: ['Animations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des animations, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Animation'))
            ),
        ]
    )]
    public function index()
    {
        return AnimationResource::collection(Animation::orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/animations',
        summary: 'Créer une animation',
        tags: ['Animations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AnimationInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Animation créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Animation')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreAnimationRequest $request)
    {
        $animation = Animation::create($request->validated());

        return new AnimationResource($animation);
    }

    #[OA\Get(
        path: '/api/animations/{animation}',
        summary: 'Détail d\'une animation',
        tags: ['Animations'],
        parameters: [
            new OA\Parameter(name: 'animation', in: 'path', required: true, description: 'ID de l\'animation', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Animation trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/Animation')
            ),
            new OA\Response(response: 404, description: 'Animation introuvable'),
        ]
    )]
    public function show(Animation $animation)
    {
        return new AnimationResource($animation);
    }

    #[OA\Put(
        path: '/api/animations/{animation}',
        summary: 'Mettre à jour une animation',
        tags: ['Animations'],
        parameters: [
            new OA\Parameter(name: 'animation', in: 'path', required: true, description: 'ID de l\'animation', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AnimationInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Animation mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Animation')
            ),
            new OA\Response(response: 404, description: 'Animation introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateAnimationRequest $request, Animation $animation)
    {
        $animation->update($request->validated());

        return new AnimationResource($animation);
    }

    #[OA\Delete(
        path: '/api/animations/{animation}',
        summary: 'Supprimer une animation',
        tags: ['Animations'],
        parameters: [
            new OA\Parameter(name: 'animation', in: 'path', required: true, description: 'ID de l\'animation', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Animation supprimée'),
            new OA\Response(response: 404, description: 'Animation introuvable'),
        ]
    )]
    public function destroy(Animation $animation)
    {
        $animation->delete();

        return new AnimationResource($animation);
    }
}
