<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $business = $request->attributes->get('current_business');
        $sub = $business?->subscription()->with('plan')->first();
        if (!$sub) return response()->json(['success'=>false,'message'=>'No subscription found','redirect'=>'/pricing'], 402);
        if ($sub->status === 'trial' && $sub->trial_ends_at && now()->greaterThan($sub->trial_ends_at)) {
            $sub->update(['status' => 'expired']);
            return response()->json(['success'=>false,'message'=>'Trial expired','redirect'=>'/pricing'], 402);
        }
        if (!in_array($sub->status, ['trial','active'], true)) return response()->json(['success'=>false,'message'=>'Subscription inactive','redirect'=>'/pricing'], 402);
        if ($sub->ends_at && now()->greaterThan($sub->ends_at)) {
            $sub->update(['status' => 'expired']);
            return response()->json(['success'=>false,'message'=>'Subscription expired','redirect'=>'/pricing'], 402);
        }
        return $next($request);
    }
}
