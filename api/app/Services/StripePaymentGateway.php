<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    public function createCheckout(int $amount, array $metadata): array
    {
        $stripe = new StripeClient((string) config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => rtrim((string) config('app.url'), '/').'/checkout/success',
            'cancel_url' => rtrim((string) config('app.url'), '/').'/checkout/cancel',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'vnd',
                    'unit_amount' => $amount,
                    'product_data' => ['name' => 'Lịch hẹn'],
                ],
            ]],
            'metadata' => $metadata,
        ]);

        return [
            'id' => (string) $session->id,
            'url' => (string) $session->url,
            'payment_intent_id' => (string) $session->payment_intent,
            'expires_at' => CarbonImmutable::createFromTimestamp((int) $session->expires_at)->toIso8601String(),
        ];
    }

    public function refund(string $paymentIntentId, int $amount): string
    {
        $stripe = new StripeClient((string) config('services.stripe.secret'));
        $refund = $stripe->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
        ]);

        return (string) $refund->id;
    }
}
