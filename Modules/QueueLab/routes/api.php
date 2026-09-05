<?php

use Illuminate\Support\Facades\Route;
use Modules\QueueLab\Http\Controllers\QueueLabController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('queuelabs', QueueLabController::class)->names('queuelab');
});
