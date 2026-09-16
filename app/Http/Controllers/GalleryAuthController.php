<?php

namespace App\Http\Controllers;

use App\Http\Resources\GalleryGuestResource;
use App\Models\GalleryGuest;
use App\Models\GallerySettings;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class GalleryAuthController extends Controller
{
    #[OA\Get(
        path: '/api/gallery/invite/{token}',
        summary: "Vérifier un token d'invitation (page d'accueil du QR code)",
        tags: ['Gallery'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Token valide, informations du mariage'),
            new OA\Response(response: 404, description: 'Token invalide'),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function checkInvite(string $token)
    {
        $settings = $this->settingsForValidToken($token);

        $wedding = Wedding::first();

        return response()->json([
            'wedding' => [
                'spouse_1_name' => $wedding?->spouse_1_name,
                'spouse_2_name' => $wedding?->spouse_2_name,
                'date' => $wedding?->date?->toDateString(),
            ],
            'registrations_open' => $settings->registrationsAllowed(),
        ]);
    }

    #[OA\Post(
        path: '/api/gallery/register',
        summary: "Créer un compte invité via le token d'invitation",
        tags: ['Gallery'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'name', 'pin'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'name', type: 'string', example: 'Camille'),
                    new OA\Property(property: 'pin', type: 'string', example: '4821'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé, token de session retourné'),
            new OA\Response(response: 404, description: "Token d'invitation invalide"),
            new OA\Response(response: 422, description: 'Erreur de validation ou inscriptions fermées'),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function register(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $settings = $this->settingsForValidToken($data['token']);

        if (! $settings->registrationsAllowed()) {
            throw ValidationException::withMessages([
                'token' => ['Les inscriptions sont fermées. Contactez les mariés.'],
            ]);
        }

        $normalized = GalleryGuest::normalizeName($data['name']);

        if (GalleryGuest::where('name_normalized', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Ce prénom est déjà pris — ajoutez une initiale ou un surnom.'],
            ]);
        }

        $guest = GalleryGuest::create([
            'name' => trim($data['name']),
            'name_normalized' => $normalized,
            'pin_hash' => Hash::make($data['pin']),
            'created_ip' => $request->ip(),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'token' => $guest->createToken('gallery')->plainTextToken,
            'guest' => new GalleryGuestResource($guest),
        ], 201);
    }

    #[OA\Post(
        path: '/api/gallery/login',
        summary: 'Reconnexion d\'un invité (prénom + PIN)',
        tags: ['Gallery'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'pin'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Camille'),
                    new OA\Property(property: 'pin', type: 'string', example: '4821'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connecté, token de session retourné'),
            new OA\Response(response: 422, description: 'Identifiants incorrects'),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function login(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);

        $guest = GalleryGuest::where('name_normalized', GalleryGuest::normalizeName($data['name']))->first();

        if ($guest === null || $guest->isBanned() || ! Hash::check($data['pin'], $guest->pin_hash)) {
            throw ValidationException::withMessages([
                'name' => ['Prénom ou code PIN incorrect.'],
            ]);
        }

        $guest->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $guest->createToken('gallery')->plainTextToken,
            'guest' => new GalleryGuestResource($guest),
        ]);
    }

    #[OA\Get(
        path: '/api/gallery/me',
        summary: "Profil de l'invité connecté",
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'Profil', content: new OA\JsonContent(ref: '#/components/schemas/GalleryGuest')),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function me(Request $request)
    {
        return new GalleryGuestResource($request->user()->loadCount('photos'));
    }

    #[OA\Post(
        path: '/api/gallery/logout',
        summary: "Déconnexion de l'invité (révoque le token courant)",
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'Déconnecté'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function settingsForValidToken(string $token): GallerySettings
    {
        $settings = GallerySettings::current();

        abort_unless(hash_equals($settings->invite_token, $token), 404);

        return $settings;
    }
}
