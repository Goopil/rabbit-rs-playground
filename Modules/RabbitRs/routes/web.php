<?php

use Illuminate\Support\Facades\Route;
use Modules\RabbitRs\Http\Controllers\RabbitRsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('rabbitrs', RabbitRsController::class)->names('rabbitrs');
});
