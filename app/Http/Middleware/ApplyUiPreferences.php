<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyUiPreferences
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preferences = $request->user()?->ui_preferences ?? [];
        $locale = $preferences['locale'] ?? 'en';
        App::setLocale(in_array($locale, ['en', 'fil'], true) ? $locale : 'en');
        view()->share('uiPreferences', $preferences);

        return $next($request);
    }
}
