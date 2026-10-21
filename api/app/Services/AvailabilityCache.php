<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

class AvailabilityCache
{
    /**
     * @param  list<string>  $servicePublicIds
     */
    public static function key(int $tenantId, string $branchPublicId, string $staffPublicId, array $servicePublicIds, string $date): string
    {
        $servicePublicIds = array_values($servicePublicIds);
        sort($servicePublicIds);

        return 'availability:'.$tenantId.':'.$branchPublicId.':'.$staffPublicId.':'.implode(',', $servicePublicIds).':'.$date;
    }

    /**
     * @param  list<string>  $servicePublicIds
     */
    public function remember(int $tenantId, string $branchPublicId, string $staffPublicId, array $servicePublicIds, string $date, callable $callback): mixed
    {
        return Cache::remember(self::key($tenantId, $branchPublicId, $staffPublicId, $servicePublicIds, $date), 30, $callback);
    }

    public function forgetAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['branch', 'staff', 'services.service']);
        $branch = $appointment->branch;
        $staff = $appointment->staff;

        if ($branch === null || $staff === null) {
            return;
        }

        $servicePublicIds = $appointment->services
            ->map(fn ($row): ?string => $row->service?->public_id)
            ->filter()
            ->values()
            ->all();

        Cache::forget(self::key(
            (int) $appointment->tenant_id,
            $branch->public_id,
            $staff->public_id,
            $servicePublicIds,
            $appointment->starts_at->timezone($branch->timezone)->toDateString(),
        ));
    }

    public function forgetBranch(int $tenantId, string $branchPublicId): void
    {
        $this->forgetFragment('availability:'.$tenantId.':'.$branchPublicId.':');
    }

    public function forgetStaff(int $tenantId, string $staffPublicId): void
    {
        $this->forgetFragment(':'.$staffPublicId.':', 'availability:'.$tenantId.':');
    }

    private function forgetFragment(string $fragment, string $startsWith = 'availability:'): void
    {
        $store = Cache::getStore();
        $prefix = $store->getPrefix();

        if ($store instanceof ArrayStore) {
            foreach (array_keys($store->all(false)) as $stored) {
                $logical = str_starts_with($stored, $prefix) ? substr($stored, strlen($prefix)) : $stored;

                if (str_starts_with($logical, $startsWith) && str_contains($logical, $fragment)) {
                    Cache::forget($logical);
                }
            }

            return;
        }

        if (! $store instanceof RedisStore) {
            return;
        }

        $connection = $store->connection();
        $keys = $connection->keys($prefix.'*'.$fragment.'*');

        if (! is_array($keys)) {
            return;
        }

        foreach ($keys as $key) {
            $connection->del($key);
        }
    }
}
