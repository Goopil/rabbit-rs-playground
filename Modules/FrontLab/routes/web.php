<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontLab\Http\Controllers\DashboardController;
use Modules\FrontLab\Http\Controllers\DispatchController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lab', [DashboardController::class, 'index'])->name('lab.dashboard');
    Route::post('/lab/dispatch', [DispatchController::class, 'store'])->name('lab.dispatch');
});
