<?php

namespace App\Services;

use App\Exceptions\IdempotencyReplay;
use App\Models\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class IdempotencyStore
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function hash(array $data): string
    {
        $serviceIds = array_values($data['service_ids']);
        sort($serviceIds);

        return hash('sha256', json_encode([
            'branch_id' => $data['branch_id'],
            'staff_id' => $data['staff_id'],
            'service_ids' => $serviceIds,
            'starts_at' => $data['starts_at'],
            'customer' => [
                'name' => $data['customer']['name'],
                'phone' => $data['customer']['phone'],
                'email' => $data['customer']['email'] ?? null,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function replay(int $tenantId, string $key, string $hash): ?JsonResponse
    {
        $row = IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        if ($row === null || $row->expires_at->isPast()) {
            return null;
        }

        if (! hash_equals($row->request_hash, $hash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Key đã dùng cho nội dung khác.',
            ]);
        }

        return response()->json($row->response_body, $row->response_status);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function store(int $tenantId, string $key, string $hash, array $body): void
    {
        $existing = IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        $payload = [
            'request_hash' => $hash,
            'response_status' => 201,
            'response_body' => $body,
            'expires_at' => now()->addDay(),
        ];

        if ($existing === null) {
            try {
                IdempotencyKey::query()->create([
                    'tenant_id' => $tenantId,
                    'key' => $key,
                    ...$payload,
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->reread($tenantId, $key, $hash);
            }

            return;
        }

        if ($existing->expires_at->isFuture() && ! hash_equals($existing->request_hash, $hash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Key đã dùng cho nội dung khác.',
            ]);
        }

        if ($existing->expires_at->isFuture()) {
            throw new IdempotencyReplay($existing->response_body, $existing->response_status);
        }

        $existing->update($payload);
    }

    private function reread(int $tenantId, string $key, string $hash): void
    {
        $row = IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        if ($row !== null && hash_equals($row->request_hash, $hash)) {
            throw new IdempotencyReplay($row->response_body, $row->response_status);
        }

        throw ValidationException::withMessages([
            'idempotency_key' => 'Key đã dùng cho nội dung khác.',
        ]);
    }
}
