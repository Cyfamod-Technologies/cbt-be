<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPermissionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        $staff = $user->isAdmin() && $request->integer('staff_id')
            ? Staff::where('school_id', $user->school_id)->findOrFail($request->integer('staff_id'))
            : $user->staff;

        abort_unless($staff instanceof Staff && $staff->school_id === $user->school_id, 404);

        $staff->load([
            'courseAssignments.session',
            'courseAssignments.semester',
            'courseAssignments.department',
            'courseAssignments.level',
            'courseAssignments.course',
            'examOfficerAssignments.session',
            'examOfficerAssignments.semester',
            'examOfficerAssignments.department',
            'examOfficerAssignments.level',
        ]);

        return response()->json([
            'data' => [
                'staff' => $staff,
                'course_assignments' => $staff->courseAssignments,
                'exam_officer_assignments' => $staff->examOfficerAssignments,
                'can_manage_assigned_courses' => $staff->courseAssignments->where('status', 'active')->isNotEmpty(),
                'can_manage_exam_officer_scope' => $staff->examOfficerAssignments->where('status', 'active')->isNotEmpty(),
            ],
        ]);
    }
}
