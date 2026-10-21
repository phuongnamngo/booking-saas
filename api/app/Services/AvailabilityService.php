<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * @param  Collection<int, Service>  $services
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function slots(Branch $branch, StaffProfile $staff, Collection $services, string $date, ?int $ignoreAppointmentId = null): array
    {
        if ($services->isEmpty() || ! $this->staffCanPerform($branch, $staff, $services)) {
            return [];
        }

        $day = CarbonImmutable::parse($date, $branch->timezone)->startOfDay();

        if ($branch->holidays()->whereDate('date', $day->toDateString())->exists()) {
            return [];
        }

        $duration = (int) $services->sum('duration_minutes');
        $open = $this->clockIntervals($branch->openingHours()->where('weekday', $day->dayOfWeek)->get(), $day, 'opens_at', 'closes_at');
        $work = $this->clockIntervals($staff->workingHours()->where('weekday', $day->dayOfWeek)->get(), $day, 'starts_at', 'ends_at');

        $dayStartUtc = $day->utc()->format('Y-m-d H:i:s');
        $dayEndUtc = $day->endOfDay()->utc()->format('Y-m-d H:i:s');

        $exceptions = $staff->scheduleExceptions()
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        foreach ($exceptions->where('type', 'extra') as $extra) {
            $work[] = [$extra->starts_at->toImmutable(), $extra->ends_at->toImmutable()];
        }

        $blocked = $exceptions->where('type', 'off')->map(
            fn ($off): array => [$off->starts_at->toImmutable(), $off->ends_at->toImmutable()],
        )->all();

        $appointments = Appointment::query()
            ->where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get();

        $slots = [];
        for ($cursor = $day; $cursor->lessThan($day->endOfDay()); $cursor = $cursor->addMinutes(15)) {
            $slotEnd = $cursor->addMinutes($duration);
            if (
                $this->containedBy($cursor, $slotEnd, $open)
                && $this->containedBy($cursor, $slotEnd, $work)
                && ! $this->overlaps($cursor, $slotEnd, $blocked)
                && ! $this->overlaps($cursor, $slotEnd, $appointments->map(
                    fn (Appointment $appointment): array => [$appointment->starts_at->toImmutable(), $appointment->ends_at->toImmutable()],
                )->all())
            ) {
                $slots[] = [
                    'starts_at' => $cursor->utc(),
                    'ends_at' => $slotEnd->utc(),
                ];
            }
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function staffCanPerform(Branch $branch, StaffProfile $staff, Collection $services): bool
    {
        $assigned = $staff->user()->whereHas(
            'branches',
            fn ($branches) => $branches->whereKey($branch->id),
        )->exists();

        if (! $assigned) {
            return false;
        }

        $required = $services->pluck('id')->unique()->values();
        $covered = $staff->services()->whereIn('services.id', $required)->pluck('services.id');

        return $covered->unique()->count() === $required->count();
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function clockIntervals(Collection $rows, CarbonImmutable $day, string $startKey, string $endKey): array
    {
        return $rows->map(function ($row) use ($day, $startKey, $endKey): array {
            return [
                CarbonImmutable::parse($day->toDateString().' '.$row->{$startKey}, $day->timezone),
                CarbonImmutable::parse($day->toDateString().' '.$row->{$endKey}, $day->timezone),
            ];
        })->all();
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function containedBy(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            if ($start->greaterThanOrEqualTo($intervalStart) && $end->lessThanOrEqualTo($intervalEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function overlaps(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            if ($start->lessThan($intervalEnd) && $end->greaterThan($intervalStart)) {
                return true;
            }
        }

        return false;
    }
}
