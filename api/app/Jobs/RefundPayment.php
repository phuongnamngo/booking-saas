<?php

namespace App\Jobs;

use App\Mail\RefundReceiptMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\InvoiceIssuer;
use App\Services\PaymentGateway;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RefundPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public int $paymentId, public int $tenantId) {}

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function handle(PaymentGateway $gateway): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null || $payment->status === 'refunded') {
            return;
        }

        if ($payment->refunds()->where('status', 'succeeded')->exists()) {
            $payment->update(['status' => 'refunded']);

            return;
        }

        $payment->update(['status' => 'refund_pending']);
        $email = $payment->appointment()->first()?->customer()->first()?->email;

        try {
            $gatewayRefundId = $gateway->refund((string) $payment->payment_intent_id, (int) $payment->amount);
            DB::transaction(function () use ($payment, $gatewayRefundId, $email): void {
                $refund = $payment->refunds()->create([
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => 'succeeded',
                    'gateway_refund_id' => $gatewayRefundId,
                ]);
                app(InvoiceIssuer::class)->markRefunded($payment, $refund);
                $payment->update(['status' => 'refunded']);
                $invoice = $payment->invoice()->first();

                if ($invoice !== null && is_string($email) && $email !== '') {
                    $invoiceId = $invoice->id;
                    DB::afterCommit(function () use ($invoiceId, $email): void {
                        $fresh = Invoice::query()->find($invoiceId);

                        if ($fresh !== null) {
                            Mail::to($email)->queue(new RefundReceiptMail($fresh));
                        }
                    });
                }
            });
        } catch (Throwable $exception) {
            $payment->update(['status' => 'refund_pending']);

            throw $exception;
        }
    }
}
