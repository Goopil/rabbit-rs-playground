<?php

use Illuminate\Support\Facades\Route;
use Modules\RabbitRs\Http\Controllers\RabbitRsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('rabbitrs', RabbitRsController::class)->names('rabbitrs');
});
