<?php

namespace App\Http\Middleware;

use App\Models\BusinessUser;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        $business = $request->attributes->get('current_business');
        if ($user?->is_super_admin) return $next($request);
        $membership = BusinessUser::where('business_id', $business?->id)
            ->where('user_id', $user?->id)->where('status','active')->with('role.permissions')->first();
        $allowed = $membership?->role?->permissions?->contains('code', $permission);
        if (!$allowed) return response()->json(['success'=>false,'message'=>'Permission denied: '.$permission], 403);
        return $next($request);
    }
}
