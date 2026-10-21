<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function write(?User $actor, string $action, Model $subject, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_public_id' => (string) $subject->getAttribute('public_id'),
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * @return array{status: string, starts_at: string, ends_at: string, staff_id: ?string}
     */
    public function appointmentState(Appointment $appointment): array
    {
        $appointment->loadMissing('staff');

        return [
            'status' => $appointment->status,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
            'staff_id' => $appointment->staff?->public_id,
        ];
    }
}
