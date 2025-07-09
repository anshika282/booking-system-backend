<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantManager;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (\Exception $e) {
            // This case should ideally be caught by the 'auth:api' middleware first
            return response()->json(['error' => 'Unauthorized. Token is invalid or expired.'], 401);
        }

        $tenantIdFromToken = $payload->get('tenant_id');

        // 1. Check if user and tenant_id from token exist
        if (!$user || !$tenantIdFromToken) {
            return response()->json(['error' => 'Forbidden. Invalid token context.'], 403);
        }

        // 2. The crucial check: Does the authenticated user belong to the tenant in the token?
        if ($user->tenant_id != $tenantIdFromToken) {
            return response()->json(['error' => 'Forbidden. You do not have permission to access this tenant.'], 403);
        }

        // 3. If validation passes, set the tenant ID in our global service
        // We can now safely trust this tenant_id for the rest of the request.
        \Log::info("tenant id is : " . $tenantIdFromToken);
        app(TenantManager::class)->setCurrentTenantId((int) $tenantIdFromToken);

        return $next($request);
    }
}
