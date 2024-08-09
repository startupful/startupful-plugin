<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale');

        if (!$locale) {
            $locale = $this->getPreferredLanguage($request);
        }

        if (!in_array($locale, config('app.available_locales'))) {
            $locale = config('app.fallback_locale');
        }

        App::setLocale($locale);
        session(['locale' => $locale]);
        
        return $next($request);
    }

    private function getPreferredLanguage(Request $request)
    {
        $languages = explode(',', $request->server('HTTP_ACCEPT_LANGUAGE'));
        $languages = array_map(function ($lang) {
            return substr(trim($lang), 0, 2);
        }, $languages);

        foreach ($languages as $lang) {
            if (in_array($lang, config('app.available_locales'))) {
                return $lang;
            }
        }

        return config('app.fallback_locale');
    }
}