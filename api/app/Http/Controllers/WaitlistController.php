<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string'],
            'staff_id' => ['required', 'string'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['string'],
            'starts_at' => ['required', 'date'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:32'],
            'customer.email' => ['required', 'email'],
        ], [
            'customer.email.required' => 'Cần email để báo khi có giờ.',
        ]);

        $branch = Branch::query()->where('public_id', $data['branch_id'])->firstOrFail();
        $staff = StaffProfile::query()->where('public_id', $data['staff_id'])->firstOrFail();
        WaitlistEntry::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'service_public_ids' => array_values($data['service_ids']),
            'starts_at' => CarbonImmutable::parse($data['starts_at']),
            'customer_name' => $data['customer']['name'],
            'customer_phone' => trim($data['customer']['phone']),
            'customer_email' => $data['customer']['email'],
        ]);

        return response()->json(['status' => 'waiting'], 201);
    }
}
