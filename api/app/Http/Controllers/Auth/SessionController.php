<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScope('tenant')->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 422);
        }

        if ($user->tenant?->isSuspended()) {
            return response()->json([
                'message' => 'Tenant đang bị khóa.',
            ], 403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'id' => $user->public_id,
        ]);
    }

    public function show(Request $request): UserResource
    {
        $user = $request->user();

        if ($user->tenant_id !== null) {
            $user->load('tenant');
            app(TenantContext::class)->set($user->tenant);
            setPermissionsTeamId($user->tenant_id);
        }

        return new UserResource($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }
}
