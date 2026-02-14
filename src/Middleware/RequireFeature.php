<?php

namespace MugiWara\FeatureFlags\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use MugiWara\FeatureFlags\Facades\Feature;

class RequireFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $feature      The feature flag name to check.
     * @param  string  $responseType How to respond when the feature is disabled:
     *                               'abort'     — 404 Not Found (default)
     *                               'forbidden' — 403 Forbidden
     *                               'json'      — 404 JSON {"error": "Feature not available"}
     *                               'redirect'  — redirect to 'home' route with error flash
     */
    public function handle(Request $request, Closure $next, string $feature, string $responseType = 'abort'): Response
    {
        if (Feature::isDisabled($feature)) {
            return match ($responseType) {
                'forbidden' => abort(403, 'This feature is disabled.'),
                'json'      => response()->json(['error' => 'Feature not available'], 404),
                'redirect'  => redirect()->route('home')->with('error', 'This feature is not available.'),
                default     => abort(404),  // covers 'abort' and any unrecognised value
            };
        }

        return $next($request);
    }
}