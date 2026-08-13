<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSimulationRequest;
use App\Http\Requests\UpdateSimulationRequest;
use App\Http\Resources\SimulationResource;
use App\Models\Simulation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SimulationController extends Controller
{
    #[OA\Get(
        path: '/api/simulations',
        summary: 'Liste des simulations de budget',
        tags: ['Simulations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des simulations',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Simulation'))
            ),
        ]
    )]
    public function index()
    {
        return SimulationResource::collection(
            Simulation::with(['venue', 'caterer', 'florist', 'animations', 'outfits'])->orderBy('created_at')->get()
        );
    }

    #[OA\Post(
        path: '/api/simulations',
        summary: 'Créer une simulation',
        tags: ['Simulations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SimulationInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Simulation créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Simulation')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreSimulationRequest $request)
    {
        $simulation = Simulation::create([...$request->validated(), 'is_active' => false]);

        return new SimulationResource($simulation);
    }

    #[OA\Get(
        path: '/api/simulations/{simulation}',
        summary: 'Détail d\'une simulation',
        tags: ['Simulations'],
        parameters: [
            new OA\Parameter(name: 'simulation', in: 'path', required: true, description: 'ID de la simulation', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Simulation trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/Simulation')
            ),
            new OA\Response(response: 404, description: 'Simulation introuvable'),
        ]
    )]
    public function show(Simulation $simulation)
    {
        return new SimulationResource($simulation);
    }

    #[OA\Put(
        path: '/api/simulations/{simulation}',
        summary: 'Mettre à jour une simulation (renommage et/ou sélection par catégorie)',
        tags: ['Simulations'],
        parameters: [
            new OA\Parameter(name: 'simulation', in: 'path', required: true, description: 'ID de la simulation', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SimulationInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Simulation mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Simulation')
            ),
            new OA\Response(response: 404, description: 'Simulation introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateSimulationRequest $request, Simulation $simulation)
    {
        $validated = $request->validated();
        $animationIds = Arr::pull($validated, 'animation_ids');
        $outfitIds = Arr::pull($validated, 'outfit_ids');

        $simulation->update($validated);

        if ($animationIds !== null) {
            $simulation->animations()->sync($animationIds);
        }

        if ($outfitIds !== null) {
            $simulation->outfits()->sync($outfitIds);
        }

        return new SimulationResource($simulation);
    }

    #[OA\Patch(
        path: '/api/simulations/{simulation}/activate',
        summary: 'Basculer la simulation active',
        tags: ['Simulations'],
        parameters: [
            new OA\Parameter(name: 'simulation', in: 'path', required: true, description: 'ID de la simulation', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Simulation activée',
                content: new OA\JsonContent(ref: '#/components/schemas/Simulation')
            ),
            new OA\Response(response: 404, description: 'Simulation introuvable'),
        ]
    )]
    public function activate(Simulation $simulation)
    {
        DB::transaction(function () use ($simulation) {
            // Mise à jour modèle par modèle (pas une requête de masse) pour que
            // les events Eloquent se déclenchent et diffusent le changement en
            // temps réel à la simulation désactivée aussi.
            Simulation::where('is_active', true)
                ->where('id', '!=', $simulation->id)
                ->get()
                ->each(fn (Simulation $active) => $active->update(['is_active' => false]));

            $simulation->update(['is_active' => true]);
        });

        return new SimulationResource($simulation);
    }

    #[OA\Delete(
        path: '/api/simulations/{simulation}',
        summary: 'Supprimer une simulation',
        description: 'Si la simulation active est supprimée, une autre est automatiquement activée (s\'il en reste).',
        tags: ['Simulations'],
        parameters: [
            new OA\Parameter(name: 'simulation', in: 'path', required: true, description: 'ID de la simulation', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Simulation supprimée'),
            new OA\Response(response: 404, description: 'Simulation introuvable'),
        ]
    )]
    public function destroy(Simulation $simulation)
    {
        $wasActive = $simulation->is_active;
        $simulation->delete();

        // S'il en reste, on en réactive une pour qu'il y ait toujours un contexte
        // courant tant qu'au moins une simulation existe.
        if ($wasActive) {
            Simulation::oldest('id')->first()?->update(['is_active' => true]);
        }

        return response()->json([
            'message' => 'Simulation supprimée',
        ]);
    }
}
