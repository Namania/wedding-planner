<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimelineEventRequest;
use App\Http\Requests\UpdateTimelineEventRequest;
use App\Http\Resources\TimelineEventResource;
use App\Models\TimelineEvent;
use OpenApi\Attributes as OA;

class TimelineEventController extends Controller
{
    #[OA\Get(
        path: '/api/timeline-events',
        summary: 'Liste des événements du planning',
        tags: ['TimelineEvents'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des événements, par ordre chronologique',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TimelineEvent'))
            ),
        ]
    )]
    public function index()
    {
        return TimelineEventResource::collection(TimelineEvent::orderBy('starts_at')->get());
    }

    #[OA\Post(
        path: '/api/timeline-events',
        summary: 'Créer un événement',
        tags: ['TimelineEvents'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TimelineEventInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Événement créé',
                content: new OA\JsonContent(ref: '#/components/schemas/TimelineEvent')
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function store(StoreTimelineEventRequest $request)
    {
        $event = TimelineEvent::create($request->validated());

        return new TimelineEventResource($event);
    }

    #[OA\Get(
        path: '/api/timeline-events/{timeline_event}',
        summary: 'Détail d\'un événement',
        tags: ['TimelineEvents'],
        parameters: [
            new OA\Parameter(name: 'timeline_event', in: 'path', required: true, description: 'ID de l\'événement', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Événement trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/TimelineEvent')
            ),
            new OA\Response(response: 404, description: 'Événement introuvable'),
        ]
    )]
    public function show(TimelineEvent $timelineEvent)
    {
        return new TimelineEventResource($timelineEvent);
    }

    #[OA\Put(
        path: '/api/timeline-events/{timeline_event}',
        summary: 'Mettre à jour un événement',
        tags: ['TimelineEvents'],
        parameters: [
            new OA\Parameter(name: 'timeline_event', in: 'path', required: true, description: 'ID de l\'événement', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TimelineEventInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Événement mis à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/TimelineEvent')
            ),
            new OA\Response(response: 404, description: 'Événement introuvable'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function update(UpdateTimelineEventRequest $request, TimelineEvent $timelineEvent)
    {
        $timelineEvent->update($request->validated());

        return new TimelineEventResource($timelineEvent);
    }

    #[OA\Delete(
        path: '/api/timeline-events/{timeline_event}',
        summary: 'Supprimer un événement',
        tags: ['TimelineEvents'],
        parameters: [
            new OA\Parameter(name: 'timeline_event', in: 'path', required: true, description: 'ID de l\'événement', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Événement supprimé',
                content: new OA\JsonContent(ref: '#/components/schemas/TimelineEvent')
            ),
            new OA\Response(response: 404, description: 'Événement introuvable'),
        ]
    )]
    public function destroy(TimelineEvent $timelineEvent)
    {
        $timelineEvent->delete();

        return new TimelineEventResource($timelineEvent);
    }
}
