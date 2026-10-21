<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $routeAppointment = $request->route('appointment');
        $appointment = $routeAppointment instanceof Appointment
            ? $routeAppointment
            : Appointment::query()->where('public_id', (string) $routeAppointment)->firstOrFail();
        if ($appointment->payments()->where('status', 'succeeded')->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Lịch đã thanh toán.',
            ]);
        }

        $pending = $appointment->payments()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($pending !== null && is_string($pending->checkout_url) && $pending->checkout_url !== '') {
            return response()->json(['url' => $pending->checkout_url]);
        }

        $amount = (int) $appointment->services()->sum('price_amount');

        try {
            $session = $gateway->createCheckout($amount, [
                'appointment_public_id' => $appointment->public_id,
            ]);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Không tạo được phiên thanh toán.',
            ], 502);
        }

        $appointment->payments()->create([
            'amount' => $amount,
            'currency' => 'vnd',
            'status' => 'pending',
            'checkout_session_id' => $session['id'],
            'checkout_url' => $session['url'],
            'payment_intent_id' => $session['payment_intent_id'],
            'expires_at' => $session['expires_at'],
        ]);

        return response()->json(['url' => $session['url']]);
    }
}
