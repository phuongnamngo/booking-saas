<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()->where('slug', $request->route('slug'))->first();

        if ($tenant === null) {
            abort(404);
        }

        if ($tenant->isSuspended()) {
            abort(403, 'Tenant đang bị khóa.');
        }

        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}
