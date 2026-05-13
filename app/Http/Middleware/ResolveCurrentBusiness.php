<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;

class ResolveCurrentBusiness
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);
        $businessId = $request->header('X-Business-ID') ?: $request->query('business_id');
        if ($user->is_super_admin && $businessId) {
            $business = Business::find($businessId);
        } else {
            $query = Business::join('business_users','businesses.id','=','business_users.business_id')
                ->where('business_users.user_id', $user->id)
                ->where('business_users.status','active')
                ->select('businesses.*');
            if ($businessId) $query->where('businesses.id', $businessId);
            $business = $query->first();
        }
        if (!$business) return response()->json(['success'=>false,'message'=>'No active business found'], 403);
        if ($business->status === 'suspended') return response()->json(['success'=>false,'message'=>'Business suspended'], 403);
        $request->attributes->set('current_business', $business);
        return $next($request);
    }
}
