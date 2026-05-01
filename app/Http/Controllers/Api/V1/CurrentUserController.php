<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $user->loadMissing('school');

        if ($user->role === User::ROLE_STUDENT) {
            $user->loadMissing(['department', 'level']);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'school_id' => $user->school_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'matric_no' => $user->matric_no ?? null,
                'department_id' => $user->department_id ?? null,
                'level_id' => $user->level_id ?? null,
                'department' => isset($user->department) && $user->department
                    ? ['id' => $user->department->id, 'name' => $user->department->name]
                    : null,
                'level' => isset($user->level) && $user->level
                    ? ['id' => $user->level->id, 'name' => $user->level->name]
                    : null,
                'school' => $user->school ? [
                    'id' => $user->school->id,
                    'name' => $user->school->name,
                    'code' => $user->school->code,
                    'email' => $user->school->email,
                    'phone' => $user->school->phone,
                    'address' => $user->school->address,
                    'status' => $user->school->status,
                ] : null,
                'capabilities' => [
                    'manage_schools' => $user->canManageSchools(),
                    'manage_catalog' => $user->canManageCatalog(),
                    'manage_users' => $user->canManageUsers(),
                    'manage_questions' => $user->canManageQuestions(),
                    'manage_assessments' => $user->canManageAssessments(),
                    'take_exams' => $user->canTakeExams(),
                ],
            ],
        ]);
    }
}
