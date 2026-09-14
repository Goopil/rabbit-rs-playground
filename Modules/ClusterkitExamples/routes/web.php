<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public on purpose: the SSR render example posts its own payload, and the
// demo page proves the ClusterKit round-trip without a login detour.
Route::get('/clusterkit-demo', fn () => Inertia::render('ClusterkitExamples/Demo'))->name('clusterkit.demo');
