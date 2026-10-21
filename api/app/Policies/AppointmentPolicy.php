<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->canTouch($user, $appointment);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->canTouch($user, $appointment);
    }

    private function canTouch(User $user, Appointment $appointment): bool
    {
        if ($user->hasRole('owner')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->can('appointments.manage')
                && $appointment->branch()->whereHas(
                    'users',
                    fn ($users) => $users->whereKey($user->id),
                )->exists();
        }

        if ($user->hasRole('staff')) {
            return $user->can('appointments.handle')
                && $appointment->staff()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
