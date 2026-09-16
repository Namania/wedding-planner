<?php

namespace App\Http\Controllers;

use App\Http\Resources\GalleryPhotoResource;
use App\Models\GalleryPhoto;
use App\Models\GallerySettings;
use App\Services\GalleryPhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

class GalleryPhotoController extends Controller
{
    #[OA\Get(
        path: '/api/gallery/photos',
        summary: 'Galerie partagée (photos visibles, paginées)',
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photos visibles, de la plus récente à la plus ancienne'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index()
    {
        return GalleryPhotoResource::collection(
            GalleryPhoto::visible()->with('guest')->latest()->paginate(40)
        );
    }

    #[OA\Post(
        path: '/api/gallery/photos',
        summary: 'Uploader une photo',
        tags: ['Gallery'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['photo'],
                    properties: [
                        new OA\Property(property: 'photo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'caption', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Photo publiée', content: new OA\JsonContent(ref: '#/components/schemas/GalleryPhoto')),
            new OA\Response(response: 422, description: 'Fichier invalide ou quota atteint'),
            new OA\Response(response: 429, description: 'Trop d\'uploads, réessayez dans quelques minutes'),
        ]
    )]
    public function store(Request $request, GalleryPhotoProcessor $processor)
    {
        $maxKb = (int) config('gallery.max_upload_kb');

        $data = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', "max:{$maxKb}", 'dimensions:max_width=12000,max_height=12000'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $guest = $request->user();
        $settings = GallerySettings::current();

        if ($guest->photos()->count() >= $settings->max_photos_per_guest) {
            throw ValidationException::withMessages([
                'photo' => ["Quota atteint ({$settings->max_photos_per_guest} photos par invité)."],
            ]);
        }

        try {
            $processed = $processor->process($data['photo']);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'photo' => ['Cette image n\'a pas pu être traitée. Réessayez avec un JPEG ou un PNG.'],
            ]);
        }

        $photo = $guest->photos()->create([
            ...$processed,
            'caption' => $data['caption'] ?? null,
        ]);

        return (new GalleryPhotoResource($photo->load('guest')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/api/gallery/photos/{photo}',
        summary: 'Supprimer une de ses propres photos',
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo supprimée'),
            new OA\Response(response: 403, description: 'Photo d\'un autre invité'),
            new OA\Response(response: 404, description: 'Photo introuvable'),
        ]
    )]
    public function destroy(Request $request, GalleryPhoto $photo)
    {
        abort_unless($photo->gallery_guest_id === $request->user()->id, 403);

        $photo->delete();

        return response()->json(['message' => 'Photo supprimée.']);
    }

    #[OA\Get(
        path: '/api/gallery/photos/{photo}/{variant}',
        summary: 'Fichier image (URL signée temporaire, pas d\'auth)',
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variant', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['thumb', 'full'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contenu de l\'image'),
            new OA\Response(response: 403, description: 'Signature invalide ou expirée'),
            new OA\Response(response: 404, description: 'Photo introuvable'),
        ]
    )]
    public function file(GalleryPhoto $photo, string $variant)
    {
        abort_unless(in_array($variant, ['thumb', 'full'], true), 404);

        $path = $variant === 'thumb' ? $photo->thumb_path : $photo->path;
        $disk = Storage::disk(config('gallery.disk'));

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
