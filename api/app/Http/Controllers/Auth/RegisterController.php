<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request, RegisterOwner $registerOwner): JsonResponse
    {
        $user = $registerOwner->handle($request->validated());

        return response()->json([
            'id' => $user->public_id,
        ], 201);
    }
}
