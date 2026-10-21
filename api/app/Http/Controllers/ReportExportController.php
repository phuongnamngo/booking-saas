<?php

namespace App\Http\Controllers;

use App\Jobs\ExportReportCsv;
use App\Models\Branch;
use App\Models\ReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->can('appointments.manage')) {
            abort(403);
        }

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'string'],
            'group' => ['nullable', 'in:day,week,month'],
        ]);

        $branchId = null;
        if (! empty($data['branch_id'])) {
            $branch = Branch::query()->where('public_id', $data['branch_id'])->firstOrFail();
            if ($user->hasRole('manager') && ! $user->hasRole('owner') && ! $branch->users()->whereKey($user->id)->exists()) {
                abort(403);
            }
            $branchId = $branch->id;
        }

        $export = ReportExport::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'from_date' => $data['from'],
            'to_date' => $data['to'],
            'group' => $data['group'] ?? 'day',
            'status' => 'pending',
        ]);

        ExportReportCsv::dispatch((int) $export->id, (int) $export->tenant_id);

        return response()->json(['id' => $export->public_id], 202);
    }

    public function show(Request $request, ReportExport $export): JsonResponse|Response
    {
        $user = $request->user();
        if ($user === null || (int) $export->user_id !== (int) $user->id) {
            abort(404);
        }

        if ($export->status !== 'ready' || $export->path === null || ! Storage::disk('local')->exists($export->path)) {
            return response()->json([
                'id' => $export->public_id,
                'status' => $export->status,
            ]);
        }

        return response(Storage::disk('local')->get($export->path), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
