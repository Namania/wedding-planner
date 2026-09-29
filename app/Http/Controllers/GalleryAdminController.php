<?php

namespace App\Http\Controllers;

use App\Http\Resources\GalleryPhotoResource;
use App\Http\Resources\GallerySettingsResource;
use App\Http\Resources\GuestResource;
use App\Models\GalleryPhoto;
use App\Models\GallerySettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use ZipArchive;

class GalleryAdminController extends Controller
{
    #[OA\Get(
        path: '/api/gallery-admin/settings',
        summary: 'Réglages de la galerie (token du QR code inclus)',
        tags: ['GalleryAdmin'],
        responses: [
            new OA\Response(response: 200, description: 'Réglages', content: new OA\JsonContent(ref: '#/components/schemas/GallerySettings')),
        ]
    )]
    public function showSettings()
    {
        return new GallerySettingsResource(GallerySettings::current());
    }

    #[OA\Put(
        path: '/api/gallery-admin/settings',
        summary: 'Mettre à jour les réglages de la galerie',
        tags: ['GalleryAdmin'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GallerySettingsInput')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Réglages mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/GallerySettings')),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'registrations_open' => ['sometimes', 'boolean'],
            'registration_closes_at' => ['sometimes', 'nullable', 'date'],
            'max_guests' => ['sometimes', 'integer', 'min:1', 'max:5000'],
            'max_photos_per_guest' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $settings = GallerySettings::current();
        $settings->update($data);

        return new GallerySettingsResource($settings);
    }

    #[OA\Post(
        path: '/api/gallery-admin/settings/rotate-token',
        summary: 'Regénérer le token d\'invitation (invalide tous les QR codes imprimés)',
        tags: ['GalleryAdmin'],
        responses: [
            new OA\Response(response: 200, description: 'Nouveau token', content: new OA\JsonContent(ref: '#/components/schemas/GallerySettings')),
        ]
    )]
    public function rotateToken()
    {
        $settings = GallerySettings::current();
        $settings->rotateInviteToken();

        return new GallerySettingsResource($settings);
    }

    #[OA\Get(
        path: '/api/gallery-admin/guests',
        summary: 'Liste des comptes invités de la galerie',
        tags: ['GalleryAdmin'],
        responses: [
            new OA\Response(response: 200, description: 'Comptes invités', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/GalleryGuest'))),
        ]
    )]
    public function guests()
    {
        return GuestResource::collection(
            User::where('role', User::ROLE_GUEST)->withCount('photos')->orderBy('name')->get()
        );
    }

    #[OA\Patch(
        path: '/api/gallery-admin/guests/{guest}/ban',
        summary: 'Bannir un invité (masque aussi toutes ses photos)',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invité banni', content: new OA\JsonContent(ref: '#/components/schemas/GalleryGuest')),
            new OA\Response(response: 404, description: "Ce compte n'est pas un invité"),
        ]
    )]
    public function ban(User $guest)
    {
        $this->assertGuest($guest);

        // update() ignorerait banned_at en silence : #[Fillable] sur User ne
        // liste que name/email/password/role, à dessein (tâche 1).
        $guest->forceFill(['banned_at' => now()])->save();

        $guest->photos()->whereNull('hidden_at')->get()
            ->each(fn (GalleryPhoto $photo) => $photo->update(['hidden_at' => now()]));

        return new GuestResource($guest->loadCount('photos'));
    }

    #[OA\Patch(
        path: '/api/gallery-admin/guests/{guest}/unban',
        summary: 'Débannir un invité (ses photos restent masquées)',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invité débanni', content: new OA\JsonContent(ref: '#/components/schemas/GalleryGuest')),
            new OA\Response(response: 404, description: "Ce compte n'est pas un invité"),
        ]
    )]
    public function unban(User $guest)
    {
        $this->assertGuest($guest);

        $guest->forceFill(['banned_at' => null])->save();

        return new GuestResource($guest->loadCount('photos'));
    }

    #[OA\Post(
        path: '/api/gallery-admin/guests/{guest}/reset-password',
        summary: 'Regénérer le mot de passe d\'un invité (affiché une seule fois)',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Nouveau mot de passe en clair, à transmettre à l\'invité'),
            new OA\Response(response: 404, description: "Ce compte n'est pas un invité"),
        ]
    )]
    public function resetPassword(User $guest)
    {
        $this->assertGuest($guest);

        $password = Str::password(12, symbols: false);

        $guest->update(['password' => $password]);

        return response()->json([
            'password' => $password,
            'guest' => new GuestResource($guest),
        ]);
    }

    #[OA\Delete(
        path: '/api/gallery-admin/guests/{guest}',
        summary: 'Supprimer un compte invité et toutes ses photos',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Compte supprimé'),
            new OA\Response(response: 404, description: "Ce compte n'est pas un invité"),
        ]
    )]
    public function destroyGuest(User $guest)
    {
        $this->assertGuest($guest);

        $guest->photos()->get()->each(fn (GalleryPhoto $photo) => $photo->delete());
        $guest->delete();

        return response()->json(['message' => 'Compte invité supprimé.']);
    }

    /**
     * Ces routes lient {guest} à un User quelconque. Sans cette garde, un
     * administrateur pourrait être banni ou supprimé par le chemin prévu pour
     * les invités. 404 plutôt que 403 : on ne confirme pas son existence.
     */
    private function assertGuest(User $guest): void
    {
        abort_unless($guest->isGuest(), 404);
    }

    #[OA\Get(
        path: '/api/gallery-admin/photos',
        summary: 'Toutes les photos, y compris masquées (paginées)',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'hidden', in: 'query', description: 'Filtrer : 1 = masquées seulement', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photos'),
        ]
    )]
    public function photos(Request $request)
    {
        return GalleryPhotoResource::collection(
            GalleryPhoto::with('guest')
                ->when($request->boolean('hidden'), fn ($q) => $q->whereNotNull('hidden_at'))
                ->latest()
                ->paginate(40)
        );
    }

    #[OA\Patch(
        path: '/api/gallery-admin/photos/{photo}/hide',
        summary: 'Masquer une photo de la galerie',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo masquée', content: new OA\JsonContent(ref: '#/components/schemas/GalleryPhoto')),
        ]
    )]
    public function hide(GalleryPhoto $photo)
    {
        $photo->update(['hidden_at' => now()]);

        return new GalleryPhotoResource($photo->load('guest'));
    }

    #[OA\Patch(
        path: '/api/gallery-admin/photos/{photo}/unhide',
        summary: 'Rendre une photo à nouveau visible',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo visible', content: new OA\JsonContent(ref: '#/components/schemas/GalleryPhoto')),
        ]
    )]
    public function unhide(GalleryPhoto $photo)
    {
        $photo->update(['hidden_at' => null]);

        return new GalleryPhotoResource($photo->load('guest'));
    }

    #[OA\Delete(
        path: '/api/gallery-admin/photos/{photo}',
        summary: 'Supprimer définitivement une photo',
        tags: ['GalleryAdmin'],
        parameters: [
            new OA\Parameter(name: 'photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo supprimée'),
        ]
    )]
    public function destroyPhoto(GalleryPhoto $photo)
    {
        $photo->delete();

        return response()->json(['message' => 'Photo supprimée.']);
    }

    #[OA\Get(
        path: '/api/gallery-admin/export',
        summary: 'Télécharger toutes les photos visibles en ZIP',
        tags: ['GalleryAdmin'],
        responses: [
            new OA\Response(response: 200, description: 'Archive ZIP'),
        ]
    )]
    public function export()
    {
        $disk = Storage::disk(config('gallery.disk'));
        $zipPath = storage_path('app/gallery-export-'.now()->format('Ymd-His').'.zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        GalleryPhoto::visible()->with('guest')->orderBy('created_at')
            ->each(function (GalleryPhoto $photo) use ($zip, $disk) {
                if (! $disk->exists($photo->path)) {
                    return;
                }

                $name = sprintf(
                    '%s-%04d-%s.jpg',
                    $photo->created_at->format('Y-m-d_His'),
                    $photo->id,
                    Str::slug($photo->guest?->name ?? 'invite'),
                );

                $zip->addFromString($name, $disk->get($photo->path));
            });

        $zip->close();

        return response()->download($zipPath, 'photos-mariage.zip')->deleteFileAfterSend();
    }
}
