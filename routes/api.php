<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login'])->name('login');

// Authenticated routes group
Route::middleware('auth:api')->group(function () {
    Route::get('/user-admin', [AuthController::class, 'adminUser'])->name('user_admin');
    Route::post('/new-order', [OrderController::class, 'store']);
});

