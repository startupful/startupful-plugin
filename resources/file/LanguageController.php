<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class LanguageController extends Controller
{
    public function switch($locale)
    {
        if (in_array($locale, config('app.available_locales', ['en', 'ko', 'de', 'fr', 'hi', 'ja', 'pt', 'th', 'tl', 'zh']))) {
            session(['locale' => $locale]);
            App::setLocale($locale);
        }
        
        return redirect()->back();
    }
}