<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::post('auth/register-school', [AuthController::class, 'registerSchool'])->name('auth.register-school');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});
