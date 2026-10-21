<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportBuilder
{
    /**
     * @param  Collection<int, Branch>  $branches
     * @return array<string, mixed>
     */
    public function build(Collection $branches, string $from, string $to, string $group): array
    {
        $appointments = $this->appointments($branches, $from, $to);
        $total = $appointments->count();
        $revenue = $appointments->sum(fn (Appointment $appointment): int => $this->net($appointment));

        return [
            'revenue' => $revenue,
            'cancel_rate' => $total === 0 ? 0 : $appointments->where('status', 'cancelled')->count() / $total,
            'no_show_rate' => $total === 0 ? 0 : $appointments->where('status', 'no_show')->count() / $total,
            'peak_hours' => $this->peakHours($appointments),
            'by_period' => $this->byPeriod($appointments, $group),
            'by_service' => $this->byService($appointments),
            'by_staff' => $this->byStaff($appointments),
        ];
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, Appointment>
     */
    private function appointments(Collection $branches, string $from, string $to): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        $windowStart = CarbonImmutable::parse($from.' 00:00:00', 'UTC')->subDay()->format('Y-m-d H:i:s');
        $windowEnd = CarbonImmutable::parse($to.' 00:00:00', 'UTC')->addDays(2)->format('Y-m-d H:i:s');

        return Appointment::query()
            ->with(['payments.refunds', 'services.service', 'staff.user', 'branch'])
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('starts_at', '>=', $windowStart)
            ->where('starts_at', '<', $windowEnd)
            ->get()
            ->filter(function (Appointment $appointment) use ($from, $to): bool {
                $timezone = $appointment->branch?->timezone ?? 'Asia/Ho_Chi_Minh';
                $localDate = $appointment->starts_at->timezone($timezone)->toDateString();

                return $localDate >= $from && $localDate <= $to;
            })
            ->values();
    }

    private function net(Appointment $appointment): int
    {
        $net = 0;

        foreach ($appointment->payments as $payment) {
            if ($payment->status !== 'succeeded') {
                continue;
            }

            $refunded = (int) $payment->refunds->where('status', 'succeeded')->sum('amount');
            $net += (int) $payment->amount - $refunded;
        }

        return $net;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{hour: string, count: int}>
     */
    private function peakHours(Collection $appointments): array
    {
        $counts = [];

        foreach ($appointments as $appointment) {
            if ($appointment->status === 'cancelled') {
                continue;
            }

            $timezone = $appointment->branch?->timezone ?? 'Asia/Ho_Chi_Minh';
            $hour = $appointment->starts_at->timezone($timezone)->format('H');
            $counts[$hour] = ($counts[$hour] ?? 0) + 1;
        }

        ksort($counts);

        return collect($counts)->map(fn (int $count, string $hour): array => [
            'hour' => $hour,
            'count' => $count,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{period: string, revenue: int}>
     */
    private function byPeriod(Collection $appointments, string $group): array
    {
        $totals = [];

        foreach ($appointments as $appointment) {
            $timezone = $appointment->branch?->timezone ?? 'Asia/Ho_Chi_Minh';
            $local = $appointment->starts_at->timezone($timezone);
            $period = match ($group) {
                'week' => $local->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'month' => $local->format('Y-m'),
                default => $local->toDateString(),
            };
            $totals[$period] = ($totals[$period] ?? 0) + $this->net($appointment);
        }

        ksort($totals);

        return collect($totals)->map(fn (int $revenue, string $period): array => [
            'period' => $period,
            'revenue' => $revenue,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{id: string, name: string, revenue: int}>
     */
    private function byService(Collection $appointments): array
    {
        $rows = [];

        foreach ($appointments as $appointment) {
            $net = $this->net($appointment);
            $lines = $appointment->services;
            $priceTotal = (int) $lines->sum('price_amount');
            $allocated = 0;
            $lastIndex = $lines->count() - 1;

            foreach ($lines->values() as $index => $line) {
                $share = $this->share($net, $line, $priceTotal, $lines->count(), $index === $lastIndex, $allocated);
                $allocated += $share;
                $service = $line->service;
                if ($service === null) {
                    continue;
                }

                $id = $service->public_id;
                $rows[$id] ??= ['id' => $id, 'name' => $service->name, 'revenue' => 0];
                $rows[$id]['revenue'] += $share;
            }
        }

        return array_values($rows);
    }

    private function share(
        int $net,
        AppointmentService $line,
        int $priceTotal,
        int $lineCount,
        bool $last,
        int $allocated,
    ): int {
        if ($lineCount === 0 || $net === 0) {
            return 0;
        }

        if ($last) {
            return $net - $allocated;
        }

        if ($priceTotal > 0) {
            return (int) round($net * ((int) $line->price_amount / $priceTotal));
        }

        return intdiv($net, $lineCount);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{id: string, name: string, revenue: int}>
     */
    private function byStaff(Collection $appointments): array
    {
        $rows = [];

        foreach ($appointments as $appointment) {
            $staff = $appointment->staff;
            if ($staff === null) {
                continue;
            }

            $id = $staff->public_id;
            $rows[$id] ??= [
                'id' => $id,
                'name' => $staff->user?->name ?? '',
                'revenue' => 0,
            ];
            $rows[$id]['revenue'] += $this->net($appointment);
        }

        return array_values($rows);
    }
}
