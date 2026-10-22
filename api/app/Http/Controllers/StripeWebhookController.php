<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceiptMail;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\StripeEvent;
use App\Models\Tenant;
use App\Services\InvoiceIssuer;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            $eventRow = StripeEvent::query()->create(['event_id' => $event->id]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['received' => true]);
        }

        try {
            if ($event->type === 'checkout.session.completed') {
                $this->completeCheckout($event);
            }
        } catch (Throwable $exception) {
            $eventRow->delete();

            throw $exception;
        }

        return response()->json(['received' => true]);
    }

    private function completeCheckout(object $event): void
    {
        $publicId = $event->data->object->metadata->appointment_public_id ?? null;
        $appointment = is_string($publicId)
            ? Appointment::withoutGlobalScope('tenant')->where('public_id', $publicId)->first()
            : null;

        if ($appointment === null) {
            return;
        }

        $tenant = Tenant::query()->find($appointment->tenant_id);

        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
        }

        $payment = $appointment->payments()->where('status', 'pending')->latest('id')->first();

        if ($payment === null) {
            return;
        }

        DB::transaction(function () use ($event, $payment): void {
            $payment->update([
                'status' => 'succeeded',
                'payment_intent_id' => (string) $event->data->object->payment_intent,
            ]);
            $invoice = app(InvoiceIssuer::class)->issue($payment->fresh());
            $email = $payment->appointment()->first()?->customer()->first()?->email;

            if (is_string($email) && $email !== '') {
                $invoiceId = $invoice->id;
                DB::afterCommit(function () use ($invoiceId, $email): void {
                    $fresh = Invoice::query()->find($invoiceId);

                    if ($fresh !== null) {
                        Mail::to($email)->queue(new PaymentReceiptMail($fresh));
                    }
                });
            }
        });
    }
}
