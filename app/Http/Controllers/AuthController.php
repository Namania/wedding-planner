<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Durée de vie du jeton remis entre la vérification du mot de passe et
     * celle du code TOTP.
     */
    private const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(private readonly TwoFactorAuthenticator $totp) {}

    public function me(Request $request)
    {
        return $request->user();
    }

    /**
     * Première étape : mot de passe uniquement. Aucune session n'est ouverte
     * ici — le TOTP étant obligatoire, la connexion n'est jamais complète à ce
     * stade. On se contente de remettre un jeton de passage à l'étape 2.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // validate() vérifie le mot de passe sans ouvrir de session, contrairement
        // à attempt() qui connecterait l'utilisateur avant le second facteur.
        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'two_factor' => 'required',
                'challenge_token' => $this->issueChallengeToken($user),
            ]);
        }

        // Pas encore enrôlé : on (re)génère un secret en attente. Il ne devient
        // actif qu'une fois confirmé par un premier code valide.
        $secret = $this->totp->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'two_factor' => 'setup_required',
            'challenge_token' => $this->issueChallengeToken($user),
            'otpauth_uri' => $this->totp->provisioningUri($user, $secret),
            'secret' => $secret,
        ]);
    }

    /**
     * Étape 2, premier enrôlement : valide le code issu du QR code et active
     * définitivement le second facteur.
     */
    public function twoFactorSetup(Request $request)
    {
        $user = $this->userFromChallengeToken($request);

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => ['Aucun enrôlement en cours. Recommencez la connexion.'],
            ]);
        }

        $this->assertValidCode($user->two_factor_secret, $request);

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->completeLogin($request, $user);
    }

    /**
     * Étape 2, connexions suivantes : vérifie le code de l'application
     * d'authentification déjà enrôlée.
     */
    public function twoFactorChallenge(Request $request)
    {
        $user = $this->userFromChallengeToken($request);

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => ['La double authentification n\'est pas activée sur ce compte.'],
            ]);
        }

        $this->assertValidCode($user->two_factor_secret, $request);

        return $this->completeLogin($request, $user);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie.'
        ], 200);
    }

    private function completeLogin(Request $request, User $user)
    {
        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return response()->json([
            'user' => $user->fresh(),
        ]);
    }

    private function assertValidCode(string $secret, Request $request): void
    {
        $request->validate(['code' => 'required|string']);

        if (! $this->totp->verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => ['Ce code est incorrect ou a expiré.'],
            ]);
        }
    }

    /**
     * Jeton chiffré et daté, plutôt qu'un état en session : la connexion se
     * fait en deux requêtes, et cela évite de dépendre du driver de session.
     */
    private function issueChallengeToken(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->getKey(),
            'purpose' => 'two-factor',
            'expires_at' => now()->addSeconds(self::CHALLENGE_TTL_SECONDS)->timestamp,
        ]));
    }

    private function userFromChallengeToken(Request $request): User
    {
        $request->validate(['challenge_token' => 'required|string']);

        $invalid = ValidationException::withMessages([
            'challenge_token' => ['Session expirée. Reprenez la connexion depuis le début.'],
        ]);

        try {
            $payload = json_decode(
                Crypt::decryptString($request->input('challenge_token')),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|\JsonException) {
            throw $invalid;
        }

        if (($payload['purpose'] ?? null) !== 'two-factor' || ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw $invalid;
        }

        return User::find($payload['user_id'] ?? null) ?? throw $invalid;
    }
}
