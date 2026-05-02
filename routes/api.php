<?php

use App\Http\Controllers\Api\V1\AcademicSessionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AssessmentAttemptController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssessmentQuestionController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\SchoolSettingController;
use App\Http\Controllers\Api\V1\SemesterController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StaffCourseAssignmentController;
use App\Http\Controllers\Api\V1\StaffExamOfficerController;
use App\Http\Controllers\Api\V1\StudentCourseEnrollmentController;
use App\Http\Controllers\Api\V1\StaffPermissionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::post('auth/register-school', [AuthController::class, 'registerSchool'])->name('auth.register-school');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/student-access', [AuthController::class, 'studentAccess'])->name('auth.student-access');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/student-change-password', [AuthController::class, 'studentChangePassword'])->name('auth.student-change-password');

        Route::get('sessions', [AcademicSessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [AcademicSessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [AcademicSessionController::class, 'show'])->name('sessions.show');
        Route::put('sessions/{session}', [AcademicSessionController::class, 'update'])->name('sessions.update');
        Route::patch('sessions/{session}/activate', [AcademicSessionController::class, 'activate'])->name('sessions.activate');
        Route::patch('sessions/{session}/deactivate', [AcademicSessionController::class, 'deactivate'])->name('sessions.deactivate');
        Route::patch('sessions/{session}/current', [AcademicSessionController::class, 'setCurrent'])->name('sessions.current');
        Route::delete('sessions/{session}', [AcademicSessionController::class, 'destroy'])->name('sessions.destroy');

        Route::get('semesters', [SemesterController::class, 'index'])->name('semesters.index');
        Route::post('semesters', [SemesterController::class, 'store'])->name('semesters.store');
        Route::get('semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
        Route::put('semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::patch('semesters/{semester}/activate', [SemesterController::class, 'activate'])->name('semesters.activate');
        Route::patch('semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])->name('semesters.deactivate');
        Route::patch('semesters/{semester}/current', [SemesterController::class, 'setCurrent'])->name('semesters.current');
        Route::delete('semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}/activate', [DepartmentController::class, 'activate'])->name('departments.activate');
        Route::patch('departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])->name('departments.deactivate');
        Route::post('departments/{department}/levels', [DepartmentController::class, 'storeLevel'])->name('departments.levels.store');
        Route::delete('departments/{department}/levels/{level}', [DepartmentController::class, 'destroyLevel'])->name('departments.levels.destroy');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('levels', [LevelController::class, 'index'])->name('levels.index');
        Route::post('levels', [LevelController::class, 'store'])->name('levels.store');
        Route::get('levels/{level}', [LevelController::class, 'show'])->name('levels.show');
        Route::put('levels/{level}', [LevelController::class, 'update'])->name('levels.update');
        Route::patch('levels/{level}/activate', [LevelController::class, 'activate'])->name('levels.activate');
        Route::patch('levels/{level}/deactivate', [LevelController::class, 'deactivate'])->name('levels.deactivate');
        Route::delete('levels/{level}', [LevelController::class, 'destroy'])->name('levels.destroy');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::patch('courses/{course}/activate', [CourseController::class, 'activate'])->name('courses.activate');
        Route::patch('courses/{course}/deactivate', [CourseController::class, 'deactivate'])->name('courses.deactivate');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        Route::get('assessments/available', [AssessmentController::class, 'available'])->name('assessments.available');
        Route::get('assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::post('assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::put('assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
        Route::patch('assessments/{assessment}/publish', [AssessmentController::class, 'publish'])->name('assessments.publish');
        Route::patch('assessments/{assessment}/close', [AssessmentController::class, 'close'])->name('assessments.close');
        Route::get('assessments/{assessment}/questions', [AssessmentQuestionController::class, 'index'])->name('assessments.questions.index');
        Route::post('assessments/{assessment}/questions', [AssessmentQuestionController::class, 'store'])->name('assessments.questions.store');
        Route::post('assessments/{assessment}/questions/import', [AssessmentQuestionController::class, 'import'])->name('assessments.questions.import');
        Route::put('assessment-questions/{assessmentQuestion}', [AssessmentQuestionController::class, 'update'])->name('assessment-questions.update');
        Route::delete('assessment-questions/{assessmentQuestion}', [AssessmentQuestionController::class, 'destroy'])->name('assessment-questions.destroy');
        Route::get('assessment-attempts', [AssessmentAttemptController::class, 'index'])->name('assessment-attempts.index');
        Route::post('assessments/{assessment}/attempts', [AssessmentAttemptController::class, 'store'])->name('assessments.attempts.store');
        Route::get('assessment-attempts/{assessmentAttempt}', [AssessmentAttemptController::class, 'show'])->name('assessment-attempts.show');
        Route::post('assessment-attempts/{assessmentAttempt}/submit', [AssessmentAttemptController::class, 'submit'])->name('assessment-attempts.submit');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('staff/{staff}/activate', [StaffController::class, 'activate'])->name('staff.activate');
        Route::patch('staff/{staff}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::get('staff-permissions', [StaffPermissionController::class, 'show'])->name('staff-permissions.show');

        Route::get('staff-course-assignments', [StaffCourseAssignmentController::class, 'index'])->name('staff-course-assignments.index');
        Route::post('staff-course-assignments', [StaffCourseAssignmentController::class, 'store'])->name('staff-course-assignments.store');
        Route::put('staff-course-assignments/{staffCourseAssignment}', [StaffCourseAssignmentController::class, 'update'])->name('staff-course-assignments.update');
        Route::delete('staff-course-assignments/{staffCourseAssignment}', [StaffCourseAssignmentController::class, 'destroy'])->name('staff-course-assignments.destroy');

        Route::get('staff-exam-officers', [StaffExamOfficerController::class, 'index'])->name('staff-exam-officers.index');
        Route::post('staff-exam-officers', [StaffExamOfficerController::class, 'store'])->name('staff-exam-officers.store');
        Route::put('staff-exam-officers/{staffExamOfficer}', [StaffExamOfficerController::class, 'update'])->name('staff-exam-officers.update');
        Route::delete('staff-exam-officers/{staffExamOfficer}', [StaffExamOfficerController::class, 'destroy'])->name('staff-exam-officers.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::get('users/{user}/course-enrollments', [StudentCourseEnrollmentController::class, 'index'])->name('users.course-enrollments.index');
        Route::post('users/{user}/course-enrollments', [StudentCourseEnrollmentController::class, 'store'])->name('users.course-enrollments.store');
        Route::delete('users/{user}/course-enrollments/{enrollment}', [StudentCourseEnrollmentController::class, 'destroy'])->name('users.course-enrollments.destroy');

        Route::get('school-settings', [SchoolSettingController::class, 'show'])->name('school-settings.show');
        Route::patch('school-settings/current-session', [SchoolSettingController::class, 'setCurrentSession'])->name('school-settings.current-session');
        Route::patch('school-settings/current-semester', [SchoolSettingController::class, 'setCurrentSemester'])->name('school-settings.current-semester');
    });
});
