<?php

use Illuminate\Support\Facades\Route;
use Modules\QueueLab\Http\Controllers\QueueLabController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('queuelabs', QueueLabController::class)->names('queuelab');
});
