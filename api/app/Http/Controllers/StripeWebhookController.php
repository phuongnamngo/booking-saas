<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\StripeEvent;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Throwable;

class StripeWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (Throwable) {
            return response()->json(['message' => 'Chữ ký không hợp lệ.'], 400);
        }

        try {
            StripeEvent::query()->create(['event_id' => $event->id]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['received' => true]);
        }

        if ($event->type === 'checkout.session.completed') {
            $publicId = $event->data->object->metadata->appointment_public_id ?? null;
            $appointment = is_string($publicId)
                ? Appointment::withoutGlobalScope('tenant')->where('public_id', $publicId)->first()
                : null;

            if ($appointment !== null) {
                $tenant = Tenant::query()->find($appointment->tenant_id);

                if ($tenant !== null) {
                    app(TenantContext::class)->set($tenant);
                }

                $payment = $appointment->payments()->where('status', 'pending')->latest('id')->first();

                if ($payment !== null) {
                    $payment->update([
                        'status' => 'succeeded',
                        'payment_intent_id' => (string) $event->data->object->payment_intent,
                    ]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
