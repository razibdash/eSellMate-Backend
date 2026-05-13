<?php

namespace App\Http\Middleware;

use App\Models\Storefront;
use Closure;
use Illuminate\Http\Request;

class ResolveStorefrontTenant
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->header('X-Store-Subdomain')
            ?: $request->query('store')
            ?: $this->subdomainFromHost($request);

        if (!$subdomain) {
            return response()->json(['success' => false, 'message' => 'Storefront tenant could not be resolved.'], 404);
        }

        $storefront = Storefront::with('business')
            ->where('subdomain', strtolower($subdomain))
            ->first();

        if (!$storefront || !$storefront->business) {
            return response()->json(['success' => false, 'message' => 'Storefront not found.'], 404);
        }

        if ($storefront->status !== 'active' || $storefront->business->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Storefront is currently unavailable.'], 403);
        }

        $request->attributes->set('current_storefront', $storefront);
        $request->attributes->set('current_business', $storefront->business);

        return $next($request);
    }

    private function subdomainFromHost(Request $request): ?string
    {
        $host = strtolower((string) $request->getHost());
        $root = strtolower((string) config('shopbot.storefront.root_domain'));

        if (!$host || !$root || !str_ends_with($host, $root)) {
            return null;
        }

        $subdomain = rtrim(substr($host, 0, -strlen($root)), '.');

        return $subdomain !== '' ? $subdomain : null;
    }
}
