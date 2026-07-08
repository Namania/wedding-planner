<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Liste des tâches',
        tags: ['Tasks'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tâches, triées par échéance (sans échéance en dernier)',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Task'))
            ),
        ]
    )]
    public function index()
    {
        return TaskResource::collection(Task::orderBy('due_date')->orderBy('title')->get());
    }

    #[OA\Post(
        path: '/api/tasks',
        summary: 'Créer une tâche',
        tags: ['Tasks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaskInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tâche créée',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreTaskRequest $request)
    {
        $task = Task::create($request->validated());

        return new TaskResource($task);
    }

    #[OA\Get(
        path: '/api/tasks/{task}',
        summary: 'Détail d\'une tâche',
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, description: 'ID de la tâche', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tâche trouvée',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
        ]
    )]
    public function show(Task $task)
    {
        return new TaskResource($task);
    }

    #[OA\Put(
        path: '/api/tasks/{task}',
        summary: 'Mettre à jour une tâche',
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, description: 'ID de la tâche', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TaskInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tâche mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return new TaskResource($task);
    }

    #[OA\Delete(
        path: '/api/tasks/{task}',
        summary: 'Supprimer une tâche',
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, description: 'ID de la tâche', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tâche supprimée',
                content: new OA\JsonContent(ref: '#/components/schemas/Task')
            ),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
        ]
    )]
    public function destroy(Task $task)
    {
        $task->delete();

        return new TaskResource($task);
    }
}
