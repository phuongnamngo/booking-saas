<?php

namespace App\Services;

use App\Models\AppointmentService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class InvoiceIssuer
{
    public function issue(Payment $payment): Invoice
    {
        return DB::transaction(function () use ($payment): Invoice {
            $existing = Invoice::query()->where('payment_id', $payment->id)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $appointment = $payment->appointment()->firstOrFail();
            $lines = $appointment->services()
                ->with('service')
                ->orderBy('position')
                ->get()
                ->map(fn (AppointmentService $line): array => [
                    'name' => $line->service?->name ?? '',
                    'amount' => (int) $line->price_amount,
                ])
                ->all();

            return Invoice::query()->create([
                'payment_id' => $payment->id,
                'appointment_id' => $appointment->id,
                'amount' => (int) $payment->amount,
                'currency' => $payment->currency,
                'customer_name' => $appointment->customer()->first()?->name ?? '',
                'lines' => $lines,
                'issued_at' => now(),
            ]);
        });
    }

    public function markRefunded(Payment $payment, Refund $refund): void
    {
        $invoice = Invoice::query()->where('payment_id', $payment->id)->first();

        if ($invoice === null) {
            return;
        }

        $invoice->update([
            'refunded_amount' => $refund->amount,
            'refunded_at' => now(),
        ]);
    }
}
