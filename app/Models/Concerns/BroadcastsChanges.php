<?php

namespace App\Models\Concerns;

use App\Events\ResourceChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

// Diffuse automatiquement les create/update/delete de ce modèle sur le canal
// "wedding", pour que le conjoint voie les changements sans recharger la page.
// Un seul trait plutôt qu'un event/listener par modèle : tous les modèles
// concernés ont la même mécanique (créer -> diffuser la ressource API).
trait BroadcastsChanges
{
    protected static function bootBroadcastsChanges(): void
    {
        static::created(fn ($model) => static::broadcastResourceChange('created', $model));
        static::updated(fn ($model) => static::broadcastResourceChange('updated', $model));
        static::deleted(fn ($model) => static::broadcastResourceChange('deleted', $model));
    }

    protected static function broadcastResourceChange(string $action, $model): void
    {
        // Un Reverb temporairement indisponible ne doit jamais faire échouer
        // l'opération CRUD elle-même.
        try {
            ResourceChanged::dispatch(
                static::broadcastResourceName(),
                $action,
                static::broadcastResourcePayload($model),
            );
        } catch (Throwable $e) {
            Log::warning('Diffusion temps réel échouée', [
                'resource' => static::broadcastResourceName(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function broadcastResourceName(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected static function broadcastResourcePayload($model): array
    {
        $resourceClass = 'App\\Http\\Resources\\'.class_basename(static::class).'Resource';

        return (new $resourceClass($model))->toArray(request());
    }
}
