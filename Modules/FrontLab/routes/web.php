<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontLab\Http\Controllers\DashboardController;
use Modules\FrontLab\Http\Controllers\DispatchController;
use Modules\FrontLab\Http\Controllers\ExampleController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lab', [DashboardController::class, 'index'])->name('lab.dashboard');
    Route::post('/lab/dispatch', [DispatchController::class, 'store'])->name('lab.dispatch');

    Route::get('/lab/examples', [ExampleController::class, 'index'])->name('lab.examples');
    Route::post('/lab/examples/rabbit-rs', [ExampleController::class, 'rabbitRs'])->name('lab.examples.rabbit-rs');
    Route::get('/lab/examples/sentinel', [ExampleController::class, 'sentinel'])->name('lab.examples.sentinel');
});
