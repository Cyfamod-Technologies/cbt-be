<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentCourseEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentCourseEnrollmentController extends Controller
{
    public function index(Request $request, User $student): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User && $authUser->canManageUsers(), 403);
        abort_unless($student->school_id === $authUser->school_id, 404);

        return response()->json([
            'data' => StudentCourseEnrollment::with(['course.department', 'course.level', 'course.semester'])
                ->where('student_id', $student->id)
                ->get(),
        ]);
    }

    public function store(Request $request, User $student): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User && $authUser->canManageUsers(), 403);
        abort_unless(
            $student->school_id === $authUser->school_id && $student->role === User::ROLE_STUDENT,
            404,
        );

        $validated = $request->validate([
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->where('school_id', $authUser->school_id),
            ],
            'type' => ['sometimes', Rule::in(['carryover', 'elective', 'extra'])],
        ]);

        $enrollment = StudentCourseEnrollment::firstOrCreate(
            ['student_id' => $student->id, 'course_id' => $validated['course_id']],
            [
                'school_id' => $authUser->school_id,
                'type'       => $validated['type'] ?? 'carryover',
            ],
        );

        return response()->json([
            'data' => $enrollment->load(['course.department', 'course.level', 'course.semester']),
        ], 201);
    }

    public function destroy(Request $request, User $student, StudentCourseEnrollment $enrollment): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User && $authUser->canManageUsers(), 403);
        abort_unless($student->school_id === $authUser->school_id, 404);
        abort_unless($enrollment->student_id === $student->id, 404);

        $enrollment->delete();

        return response()->json(['message' => 'Enrollment removed.']);
    }
}
