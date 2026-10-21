<?php

namespace App\Services;

interface PaymentGateway
{
    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string, payment_intent_id: string, expires_at: string}
     */
    public function createCheckout(int $amount, array $metadata): array;

    public function refund(string $paymentIntentId, int $amount): string;
}
