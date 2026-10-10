<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function setupStatus(): JsonResponse
    {
        return response()->json(['required' => ! User::query()->exists()]);
    }

    public function setup(Request $request): JsonResponse
    {
        abort_if(User::query()->exists(), 409, 'A configuracao inicial ja foi concluida.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            abort_if(User::query()->lockForUpdate()->exists(), 409, 'A configuracao inicial ja foi concluida.');

            $user = User::create($validated);

            // Preserve data created before authentication was introduced.
            Chat::query()->whereNull('user_id')->update(['user_id' => $user->id]);
            Document::query()->whereNull('user_id')->update(['user_id' => $user->id]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas sao invalidas.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessao encerrada.']);
    }
}
