<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Route::get('/treat-as-https/signed', fn () => 'ok')->middleware('signed')->name('treat-as-https.signed');
    Route::getRoutes()->refreshNameLookups();

    URL::forceRootUrl('https://api.example.test');
    $this->httpsSignedUrl = URL::signedRoute('treat-as-https.signed');
    URL::forceRootUrl(null);

    // Through the proxy in front the request reaches the app looking like plain http.
    $this->arrivesAsHttp = str_replace('https://', 'http://', $this->httpsSignedUrl);
});

test('in production a link signed as https is accepted when the request arrives looking like http', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get($this->arrivesAsHttp)->assertOk();
});

test('outside production the request keeps the scheme it arrived with', function () {
    $this->get($this->arrivesAsHttp)->assertForbidden();
});

test('a link with a tampered signature is still refused in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $this->arrivesAsHttp))->assertForbidden();
});
