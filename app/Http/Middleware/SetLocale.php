<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

use function in_array;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read the 'Accept-Language' header from the request (e.g. "es", "es-ES", "es-ES,es;q=0.9,en;q=0.8").
        $header = (string) $request->header('Accept-Language', '');
        $locale = strtolower(trim(explode(',', $header)[0] ?? ''));
        $locale = strtolower(trim(explode(';', $locale)[0] ?? ''));

        // Normalize regional variants ("es-ES", "en-US") to their primary subtag ("es", "en").
        $locale = explode('-', str_replace('_', '-', $locale))[0] ?? '';
        $supportedLocales = ['es', 'en'];

        // If the requested language is supported, we apply it.
        if ($locale && in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        } else {
            // Otherwise, we use the default application locale (English).
            App::setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
