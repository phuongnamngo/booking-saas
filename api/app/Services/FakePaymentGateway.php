<?php

namespace App\Services;

class FakePaymentGateway implements PaymentGateway
{
    public int $calls = 0;

    public function createCheckout(int $amount, array $metadata): array
    {
        $this->calls++;

        return [
            'id' => 'cs_fake',
            'url' => 'https://checkout.test/fake',
            'payment_intent_id' => 'pi_fake',
            'expires_at' => now()->addHour()->toIso8601String(),
        ];
    }

    public function refund(string $paymentIntentId, int $amount): string
    {
        return 're_fake';
    }
}
