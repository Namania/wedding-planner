<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function respondWithToken(User $user, int $statusCode = 200)
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        $cookie = cookie(
            'access_token',
            $token,
            60,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'Lax'
        );

        return response()->json([
            'user' => new UserResource($user),
        ], $statusCode)->withCookie($cookie);
    }

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return $this->respondWithToken($user, 201);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $user->tokens()->delete();

        return $this->respondWithToken($user, 200);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $forgetCookie = cookie()->forget('access_token');

        return response()->json([
            'message' => 'Déconnexion réussie'
        ], 200)->withCookie($forgetCookie);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}
