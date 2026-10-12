<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use OpenApi\Attributes as OA;

class ExpenseController extends Controller
{
    #[OA\Get(
        path: '/api/expenses',
        summary: 'Dépenses réalisées',
        tags: ['Expenses'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dépenses réalisées, de la plus récente à la plus ancienne',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Expense'))
            ),
        ]
    )]
    public function index()
    {
        return ExpenseResource::collection(Expense::latest()->latest('id')->get());
    }

    #[OA\Post(
        path: '/api/expenses',
        summary: 'Enregistrer une dépense',
        tags: ['Expenses'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ExpenseInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Dépense créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Expense')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create($request->validated());

        return new ExpenseResource($expense);
    }

    #[OA\Get(
        path: '/api/expenses/{expense}',
        summary: 'Détail d\'une dépense',
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'expense', in: 'path', required: true, description: 'ID de la dépense', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dépense trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/Expense')
            ),
            new OA\Response(response: 404, description: 'Dépense introuvable'),
        ]
    )]
    public function show(Expense $expense)
    {
        return new ExpenseResource($expense);
    }

    #[OA\Put(
        path: '/api/expenses/{expense}',
        summary: 'Modifier une dépense',
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'expense', in: 'path', required: true, description: 'ID de la dépense', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ExpenseInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dépense mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Expense')
            ),
            new OA\Response(response: 404, description: 'Dépense introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());

        return new ExpenseResource($expense);
    }

    #[OA\Delete(
        path: '/api/expenses/{expense}',
        summary: 'Supprimer une dépense',
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'expense', in: 'path', required: true, description: 'ID de la dépense', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dépense supprimée',
                content: new OA\JsonContent(ref: '#/components/schemas/Expense')
            ),
            new OA\Response(response: 404, description: 'Dépense introuvable'),
        ]
    )]
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return new ExpenseResource($expense);
    }
}
