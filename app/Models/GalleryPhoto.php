<?php

namespace App\Models;

use App\Events\GalleryPhotoChanged;
use App\Http\Resources\GalleryPhotoResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GalleryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'path',
        'thumb_path',
        'width',
        'height',
        'size_bytes',
        'caption',
        'hidden_at',
    ];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(fn (self $photo) => $photo->broadcastChange('created'));
        static::updated(fn (self $photo) => $photo->broadcastChange('updated'));
        static::deleted(function (self $photo) {
            $photo->deleteFiles();
            $photo->broadcastChange('deleted');
        });
    }

    /**
     * L'invité qui a envoyé la photo. La relation garde son nom : c'est bien
     * d'un invité qu'il s'agit, même si le modèle est désormais User.
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function deleteFiles(): void
    {
        try {
            Storage::disk(config('gallery.disk'))->delete([$this->path, $this->thumb_path]);
        } catch (Throwable $e) {
            Log::warning('Suppression des fichiers photo échouée', [
                'photo' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function broadcastChange(string $action): void
    {
        try {
            GalleryPhotoChanged::dispatch(
                $action,
                (new GalleryPhotoResource($this->loadMissing('guest')))->toArray(request()),
            );
        } catch (Throwable $e) {
            Log::warning('Diffusion temps réel galerie échouée', [
                'photo' => $this->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
