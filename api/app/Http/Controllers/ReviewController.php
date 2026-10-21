<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $hash = hash('sha256', $data['token']);
        $review = Review::query()->where('token_hash', $hash)->first();

        if ($review === null || ! hash_equals($review->token_hash, $hash) || $review->used_at !== null || $review->expires_at->lte(now())) {
            throw ValidationException::withMessages([
                'token' => 'Link đánh giá không hợp lệ.',
            ]);
        }

        $appointment = $review->appointment;
        if ($appointment === null || $appointment->status !== 'completed') {
            throw ValidationException::withMessages([
                'token' => 'Link đánh giá không hợp lệ.',
            ]);
        }

        $review->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'used_at' => now(),
        ]);

        return response()->json([
            'rating' => $review->rating,
        ], 201);
    }
}
