<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use OpenApi\Attributes as OA;

class BudgetController extends Controller
{
    #[OA\Get(
        path: '/api/budget',
        summary: 'Budget total du mariage',
        tags: ['Budget'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget courant',
                content: new OA\JsonContent(ref: '#/components/schemas/Budget')
            ),
        ]
    )]
    public function show()
    {
        return new BudgetResource(Budget::firstOrCreate());
    }

    #[OA\Put(
        path: '/api/budget',
        summary: 'Mettre à jour le budget total',
        tags: ['Budget'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BudgetInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Budget')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateBudgetRequest $request)
    {
        $budget = Budget::firstOrCreate();
        $budget->update($request->validated());

        return new BudgetResource($budget);
    }
}
