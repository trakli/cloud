<?php

namespace Trakli\Cloud\Http\Middleware;

use App\Contracts\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GateFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string  $feature
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $entitlements = app(Entitlements::class);

        if (!$entitlements->allows($request->user(), $feature)) {
            abort(403, 'Unentitled.');
        }

        return $next($request);
    }
}
