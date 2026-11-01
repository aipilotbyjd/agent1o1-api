<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Placeholder landing spot for real Public/V1 resource routes (Workflows, Runs, Agents, ...).
// Confirms the ApiKey guard resolves correctly before any real resources exist.
Route::get('me', function (Request $request) {
    return response()->json([
        'workspace' => $request->attributes->get('workspace'),
    ]);
});
