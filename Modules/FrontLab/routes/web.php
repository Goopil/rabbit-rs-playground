<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontLab\Http\Controllers\FrontLabController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('frontlabs', FrontLabController::class)->names('frontlab');
});
