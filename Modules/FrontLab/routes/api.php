<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontLab\Http\Controllers\FrontLabController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('frontlabs', FrontLabController::class)->names('frontlab');
});
