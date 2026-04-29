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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'school_id' => $user->school_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
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
                    'take_exams' => $user->canTakeExams(),
                ],
            ],
        ]);
    }
}
