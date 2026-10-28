<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if (! $business instanceof Business) {
            $business = Business::whereKey($business)->firstOrFail();
        }

        $user = $request->user();

        if (! $user || ! $user->belongsToBusiness($business)) {
            abort(403, 'This action is unauthorized.');
        }

        $user->currentBusiness = $business;
        app()->instance(Business::class, $business);

        return $next($request);
    }
}
