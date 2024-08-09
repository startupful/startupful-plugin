<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    public function switch($locale)
    {
        
        if (in_array($locale, config('app.available_locales', ['en', 'ko', 'de', 'fr', 'hi', 'ja', 'pt', 'th', 'tl', 'zh']))) {
            session(['locale' => $locale]);
            App::setLocale($locale);
            Cookie::queue('locale', $locale, 60 * 24 * 30);  // 30 days
        } else {
        }
        
        return redirect()->back();
    }
}