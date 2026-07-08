<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSeatingTableRequest;
use App\Http\Requests\UpdateSeatingTableRequest;
use App\Http\Resources\SeatingTableResource;
use App\Models\SeatingTable;
use OpenApi\Attributes as OA;

class SeatingTableController extends Controller
{
    #[OA\Get(
        path: '/api/seating-tables',
        summary: 'Liste des tables du plan de table, avec leurs invités',
        tags: ['SeatingTables'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tables, par ordre alphabétique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SeatingTable'))
            ),
        ]
    )]
    public function index()
    {
        return SeatingTableResource::collection(
            SeatingTable::with('guests')->orderBy('name')->get()
        );
    }

    #[OA\Post(
        path: '/api/seating-tables',
        summary: 'Créer une table',
        tags: ['SeatingTables'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SeatingTableInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Table créée',
                content: new OA\JsonContent(ref: '#/components/schemas/SeatingTable')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreSeatingTableRequest $request)
    {
        $table = SeatingTable::create($request->validated());

        return new SeatingTableResource($table->load('guests'));
    }

    #[OA\Get(
        path: '/api/seating-tables/{seating_table}',
        summary: 'Détail d\'une table',
        tags: ['SeatingTables'],
        parameters: [
            new OA\Parameter(name: 'seating_table', in: 'path', required: true, description: 'ID de la table', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Table trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/SeatingTable')
            ),
            new OA\Response(response: 404, description: 'Table introuvable'),
        ]
    )]
    public function show(SeatingTable $seatingTable)
    {
        return new SeatingTableResource($seatingTable->load('guests'));
    }

    #[OA\Put(
        path: '/api/seating-tables/{seating_table}',
        summary: 'Mettre à jour une table',
        tags: ['SeatingTables'],
        parameters: [
            new OA\Parameter(name: 'seating_table', in: 'path', required: true, description: 'ID de la table', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SeatingTableInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Table mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/SeatingTable')
            ),
            new OA\Response(response: 404, description: 'Table introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateSeatingTableRequest $request, SeatingTable $seatingTable)
    {
        $seatingTable->update($request->validated());

        return new SeatingTableResource($seatingTable->load('guests'));
    }

    #[OA\Delete(
        path: '/api/seating-tables/{seating_table}',
        summary: 'Supprimer une table (les invités redeviennent non assignés)',
        tags: ['SeatingTables'],
        parameters: [
            new OA\Parameter(name: 'seating_table', in: 'path', required: true, description: 'ID de la table', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Table supprimée',
                content: new OA\JsonContent(ref: '#/components/schemas/SeatingTable')
            ),
            new OA\Response(response: 404, description: 'Table introuvable'),
        ]
    )]
    public function destroy(SeatingTable $seatingTable)
    {
        $seatingTable->delete();

        return new SeatingTableResource($seatingTable);
    }
}
