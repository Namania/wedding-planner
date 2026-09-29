<?php

namespace App\Http\Controllers;

use App\Http\Resources\GuestResource;
use App\Models\GallerySettings;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                required: ['token', 'name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'name', type: 'string', example: 'Camille'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@exemple.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé, session ouverte'),
            new OA\Response(response: 404, description: "Token d'invitation invalide"),
            new OA\Response(response: 422, description: 'Erreur de validation ou inscriptions fermées'),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function register(Request $request)
    {
        // Avant la validation, et pas après : `unique:users,email` cherche la
        // valeur telle qu'elle est envoyée. Normalisée seulement ensuite, une
        // adresse en capitales passerait la règle d'unicité puis heurterait
        // l'index unique de la table à l'insertion.
        $request->merge(['email' => User::normalizeEmail($request->input('email'))]);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            // Le message par défaut dirait « cet email est déjà utilisé », ce
            // qui révélerait que l'adresse des mariés est celle d'un compte.
            'email.unique' => 'Cette adresse ne peut pas être utilisée. Essayez-en une autre.',
        ]);

        $settings = $this->settingsForValidToken($data['token']);

        if (! $settings->registrationsAllowed()) {
            throw ValidationException::withMessages([
                'token' => ['Les inscriptions sont fermées. Contactez les mariés.'],
            ]);
        }

        $guest = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_GUEST,
        ]);

        $guest->forceFill(['last_seen_at' => now()])->save();

        return $this->openSession($request, $guest, 201);
    }

    #[OA\Post(
        path: '/api/gallery/login',
        summary: "Reconnexion d'un invité (email + mot de passe)",
        tags: ['Gallery'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@exemple.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connecté, session ouverte'),
            new OA\Response(response: 422, description: 'Identifiants incorrects'),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guest = User::where('email', User::normalizeEmail($data['email']))->first();

        // Un même message pour toutes les causes : compte inexistant, mot de
        // passe faux, compte d'administration ou compte banni. Les distinguer
        // dirait à un inconnu quelles adresses existent.
        if ($guest === null || ! $guest->isGuest() || $guest->isBanned()
            || ! Hash::check($data['password'], $guest->password)) {
            throw ValidationException::withMessages([
                'email' => ['Adresse ou mot de passe incorrect.'],
            ]);
        }

        $guest->forceFill(['last_seen_at' => now()])->save();

        return $this->openSession($request, $guest);
    }

    #[OA\Get(
        path: '/api/gallery/me',
        summary: "Profil de l'invité connecté",
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'Profil', content: new OA\JsonContent(ref: '#/components/schemas/GalleryAccount')),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function me(Request $request)
    {
        return new GuestResource($request->user()->loadCount('photos'));
    }

    #[OA\Post(
        path: '/api/gallery/logout',
        summary: "Déconnexion de l'invité (ferme la session)",
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'Déconnecté'),
        ]
    )]
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function settingsForValidToken(string $token): GallerySettings
    {
        $settings = GallerySettings::current();

        abort_unless(hash_equals($settings->invite_token, $token), 404);

        return $settings;
    }

    /**
     * remember: true est indispensable ici : la session dure cinq minutes, et
     * un invité sur son téléphone pendant une soirée ne doit pas la vivre.
     */
    private function openSession(Request $request, User $guest, int $status = 200)
    {
        Auth::guard('web')->login($guest, remember: true);

        $request->session()->regenerate();

        return response()->json([
            'guest' => new GuestResource($guest),
        ], $status);
    }
}
