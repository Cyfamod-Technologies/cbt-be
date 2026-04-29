<?php

use App\Http\Controllers\Api\V1\AcademicSessionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\SchoolSettingController;
use App\Http\Controllers\Api\V1\SemesterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::post('auth/register-school', [AuthController::class, 'registerSchool'])->name('auth.register-school');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('sessions', [AcademicSessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [AcademicSessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [AcademicSessionController::class, 'show'])->name('sessions.show');
        Route::put('sessions/{session}', [AcademicSessionController::class, 'update'])->name('sessions.update');
        Route::patch('sessions/{session}/activate', [AcademicSessionController::class, 'activate'])->name('sessions.activate');
        Route::patch('sessions/{session}/deactivate', [AcademicSessionController::class, 'deactivate'])->name('sessions.deactivate');
        Route::patch('sessions/{session}/current', [AcademicSessionController::class, 'setCurrent'])->name('sessions.current');

        Route::get('semesters', [SemesterController::class, 'index'])->name('semesters.index');
        Route::post('semesters', [SemesterController::class, 'store'])->name('semesters.store');
        Route::get('semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
        Route::put('semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::patch('semesters/{semester}/activate', [SemesterController::class, 'activate'])->name('semesters.activate');
        Route::patch('semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])->name('semesters.deactivate');
        Route::patch('semesters/{semester}/current', [SemesterController::class, 'setCurrent'])->name('semesters.current');

        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}/activate', [DepartmentController::class, 'activate'])->name('departments.activate');
        Route::patch('departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])->name('departments.deactivate');

        Route::get('levels', [LevelController::class, 'index'])->name('levels.index');
        Route::post('levels', [LevelController::class, 'store'])->name('levels.store');
        Route::get('levels/{level}', [LevelController::class, 'show'])->name('levels.show');
        Route::put('levels/{level}', [LevelController::class, 'update'])->name('levels.update');
        Route::patch('levels/{level}/activate', [LevelController::class, 'activate'])->name('levels.activate');
        Route::patch('levels/{level}/deactivate', [LevelController::class, 'deactivate'])->name('levels.deactivate');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::patch('courses/{course}/activate', [CourseController::class, 'activate'])->name('courses.activate');
        Route::patch('courses/{course}/deactivate', [CourseController::class, 'deactivate'])->name('courses.deactivate');

        Route::get('school-settings', [SchoolSettingController::class, 'show'])->name('school-settings.show');
        Route::patch('school-settings/current-session', [SchoolSettingController::class, 'setCurrentSession'])->name('school-settings.current-session');
        Route::patch('school-settings/current-semester', [SchoolSettingController::class, 'setCurrentSemester'])->name('school-settings.current-semester');
    });
});
