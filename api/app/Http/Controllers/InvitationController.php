<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Mail\StaffInvitationMail;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invitation::class);

        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:manager,staff'],
        ]);

        $plain = Str::random(64);

        $invitation = Invitation::query()->create([
            'email' => $data['email'],
            'role' => $data['role'],
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(Invitation::TTL_DAYS),
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invitation->email)->send(new StaffInvitationMail($plain));

        return response()->json([
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 201);
    }

    public function accept(Request $request, AcceptInvitation $acceptInvitation): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $acceptInvitation->handle($data);

        return response()->json([
            'id' => $user->public_id,
        ], 201);
    }
}
