<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Staff;
use App\Models\StaffCourseAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffCourseAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $validated = $request->validate([
            'staff_id' => ['sometimes', 'integer', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
        ]);

        return response()->json([
            'data' => StaffCourseAssignment::with(['staff', 'session', 'semester', 'department', 'level', 'course'])
                ->where('school_id', $schoolId)
                ->when($validated['staff_id'] ?? null, fn ($query, $staffId) => $query->where('staff_id', $staffId))
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        $payloads = $request->has('assignments') ? $request->input('assignments') : [$request->all()];
        $created = collect();

        foreach ($payloads as $payload) {
            $validated = validator($payload, $this->rules($actor))->validate();
            $this->validateCourseContext($actor->school_id, $validated);

            $assignment = StaffCourseAssignment::firstOrCreate(
                [
                    'school_id' => $actor->school_id,
                    'staff_id' => $validated['staff_id'],
                    'session_id' => $validated['session_id'],
                    'semester_id' => $validated['semester_id'],
                    'department_id' => $validated['department_id'],
                    'level_id' => $validated['level_id'],
                    'course_id' => $validated['course_id'],
                ],
                ['status' => $validated['status'] ?? 'active'],
            );

            if (! $assignment->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'course_id' => ['This staff course assignment already exists.'],
                ]);
            }

            $created->push($assignment->load(['staff', 'session', 'semester', 'department', 'level', 'course']));
        }

        return response()->json([
            'message' => 'Staff course assignment created successfully.',
            'data' => $request->has('assignments') ? $created->values() : $created->first(),
        ], 201);
    }

    public function destroy(Request $request, StaffCourseAssignment $staffCourseAssignment): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($staffCourseAssignment->school_id === $actor->school_id, 404);

        $staffCourseAssignment->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $actor): array
    {
        $schoolId = $actor->school_id;

        return [
            'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'session_id' => ['required', 'integer', Rule::exists('sessions', 'id')->where('school_id', $schoolId)],
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'id')->where('school_id', $schoolId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'level_id' => ['required', 'integer', Rule::exists('levels', 'id')->where('school_id', $schoolId)],
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('school_id', $schoolId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateCourseContext(int $schoolId, array $validated): void
    {
        $staff = Staff::where('school_id', $schoolId)->findOrFail($validated['staff_id']);
        $course = Course::where('school_id', $schoolId)->findOrFail($validated['course_id']);

        if (
            $staff->status !== 'active' ||
            $course->department_id !== (int) $validated['department_id'] ||
            ($course->level_id !== null && $course->level_id !== (int) $validated['level_id']) ||
            ($course->semester_id !== null && $course->semester_id !== (int) $validated['semester_id'])
        ) {
            throw ValidationException::withMessages([
                'course_id' => ['The selected course does not match the selected department, level, and semester.'],
            ]);
        }
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user->school_id;
    }

    private function requireUserManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageUsers(), 403);

        return $user;
    }
}
