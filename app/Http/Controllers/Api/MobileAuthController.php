<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPasswordVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileAuthController extends Controller
{
    public function login(Request $request, UserPasswordVerifier $passwordVerifier): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        $roleName = $user
            ? DB::table('roles')->where('id', $user->role_id)->value('role_name')
            : null;

        if (
            ! $user
            || ! $user->is_active
            || ! in_array($roleName, ['Pasien', 'Keluarga'], true)
            || ($roleName === 'Pasien' && ! DB::table('patient_profiles')->where('user_id', $user->id)->exists())
            || ! $passwordVerifier->verifyAndUpgrade($user, $validated['password'])
        ) {
            return response()->json([
                'message' => 'Email atau password salah, atau akun tidak memiliki akses aplikasi mobile.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken(
            $validated['device_name'] ?? 'Mobile App',
            ['mobile:read', 'mobile:write'],
        );

        return response()->json([
            'data' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $roleName,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token berhasil dicabut.',
        ]);
    }
}
