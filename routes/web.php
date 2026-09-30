<?php

use Illuminate\Support\Facades\Route;

// SPA Catch-all: Route all frontend views to the Vue SPA entry view
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$')->name('spa');
